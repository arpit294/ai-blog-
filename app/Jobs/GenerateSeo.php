<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AutomationRun;
use App\Services\Automation\AutomationRunStateService;

class GenerateSeo implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    protected int $runId;
    protected int $articleId;

    public function __construct(int $runId, int $articleId)
    {
        $this->runId = $runId;
        $this->articleId = $articleId;
    }

    public function handle(\App\Services\Automation\AutomationRunStateService $stateService, \App\Services\Automation\SeoGenerator $seoGenerator, \App\Services\AI\AiHealthCheck $healthCheck)
    {
        if (!$healthCheck->isHealthy()) {
            \Illuminate\Support\Facades\Log::warning("GenerateSeo: AI Providers unhealthy. Releasing job with backoff. Run: {$this->runId}");
            $this->release(60 * $this->attempts());
            return;
        }

        $run = AutomationRun::find($this->runId);
        $article = \App\Models\Article::find($this->articleId);

        if (!$run || !$article) {
            return;
        }

        // Idempotency: check if SEO already exists
        if (\App\Models\ArticleSeo::where('article_id', $article->id)->exists()) {
            \Illuminate\Support\Facades\Log::info("GenerateSeo: SEO already exists for article {$article->id}. Proceeding to QualityChecks.");
            RunQualityChecks::dispatch($this->runId, $article->id);
            return;
        }

        $stateService->moveToStage($run, 'seo_generation');

        try {
            $seoGenerator->generate($run->profile, $article, $run->id);
            $article->update(['status' => 'seo_generated']);

            \Illuminate\Support\Facades\Log::info("GenerateSeo: Successfully generated SEO for article {$article->id}.");
            
            RunQualityChecks::dispatch($this->runId, $article->id);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("GenerateSeo: Failed for run {$this->runId}: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                $stateService->markFailed($run, $e);
            }
            
            throw $e;
        }
    }
}
