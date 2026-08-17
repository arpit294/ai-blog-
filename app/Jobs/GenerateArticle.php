<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AutomationRun;
use App\Models\BlogTopic;
use App\Models\Article;
use App\Services\Automation\ContentGenerator;
use App\Services\Automation\AutomationRunStateService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateArticle implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    protected int $runId;
    protected int $topicId;

    public function __construct(int $runId, int $topicId)
    {
        $this->runId = $runId;
        $this->topicId = $topicId;
    }

    public function handle(ContentGenerator $contentGenerator, AutomationRunStateService $stateService, \App\Services\AI\AiHealthCheck $healthCheck)
    {
        if (!$healthCheck->isHealthy()) {
            \Illuminate\Support\Facades\Log::warning("GenerateArticle: AI Providers unhealthy. Releasing job with backoff. Run: {$this->runId}");
            $this->release(60 * $this->attempts()); // Exponential backoff based on attempts
            return;
        }

        $run = AutomationRun::find($this->runId);
        $topic = BlogTopic::find($this->topicId);

        if (!$run || !$topic) {
            Log::warning("GenerateArticle: Run or Topic not found.");
            return;
        }

        // Idempotency: check if article already exists for this topic
        $existingArticle = Article::where('topic_id', $this->topicId)->first();
        if ($existingArticle) {
            Log::info("GenerateArticle: Article already exists for topic {$this->topicId}. Proceeding to GenerateSeo.");
            GenerateSeo::dispatch($this->runId, $existingArticle->id);
            return;
        }

        $stateService->moveToStage($run, 'content_generation');

        try {
            $data = $contentGenerator->generate($run->profile, $topic, $run->id);

            // Persist the article
            $article = Article::create([
                'user_id' => $run->profile->user_id,
                'automation_profile_id' => $run->profile->id,
                'topic_id' => $topic->id,
                'category_id' => $run->profile->category_id,
                'title' => $data['title'],
                // Ensure slug is actually unique by appending short hash if needed or using Str::slug
                'slug' => Str::slug($data['slug']) . '-' . Str::random(5), 
                'excerpt' => $data['excerpt'] ?? null,
                'content' => json_encode($data), 
                'status' => 'content_generated',
            ]);

            // Mark topic as used
            $topic->update(['status' => 'used']);

            Log::info("GenerateArticle: Successfully generated article {$article->id}.");

            GenerateSeo::dispatch($this->runId, $article->id);

        } catch (\Exception $e) {
            Log::error("GenerateArticle: Failed for run {$this->runId}: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                // Exhausted retries
                $stateService->markFailed($run, $e);
            }
            
            throw $e;
        }
    }
}
