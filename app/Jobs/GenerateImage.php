<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\AutomationRun;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Services\Automation\AutomationRunStateService;
use App\Services\Automation\ImageGenerator;

class GenerateImage implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    protected int $runId;
    protected int $articleId;

    public function __construct(int $runId, int $articleId)
    {
        $this->runId = $runId;
        $this->articleId = $articleId;
        $this->tries = config('automation.retry_budget', 3);
    }

    public function handle(AutomationRunStateService $stateService, ImageGenerator $imageGenerator)
    {
        $run = AutomationRun::find($this->runId);
        $article = Article::find($this->articleId);

        if (!$run || !$article) {
            return;
        }

        // Target state after image generation
        $nextStage = 'review';
        $publishMode = $run->profile->publish_mode;

        if ($article->status !== 'needs_review') {
            if ($publishMode === 'auto_publish') {
                $nextStage = 'publishing';
            } elseif ($publishMode === 'scheduled') {
                $nextStage = 'scheduled';
                $article->update([
                    'status' => 'scheduled',
                    'scheduled_at' => $article->scheduled_at ?? $run->profile->next_run_at ?? now()->addDay(),
                ]);
            } elseif ($publishMode === 'draft') {
                $nextStage = 'draft';
                $article->update(['status' => 'draft']);
            }
        }

        if (!$run->profile->generate_image) {
            Log::info("GenerateImage skipped for article {$article->id} (profile disabled).");
            
            if ($nextStage === 'publishing') {
                \App\Jobs\PublishArticle::dispatch($run->id, $article->id);
            } else {
                $stateService->moveToStage($run, $nextStage);
                if (in_array($nextStage, ['review', 'scheduled', 'draft'])) {
                    $stateService->markCompleted($run);
                }
            }
            return;
        }

        // Idempotency: skip if already generated
        if (ArticleImage::where('article_id', $article->id)->where('status', 'generated')->exists()) {
            Log::info("GenerateImage: Image already exists for article {$article->id}.");
            if ($nextStage === 'publishing') {
                \App\Jobs\PublishArticle::dispatch($run->id, $article->id);
            } else {
                $stateService->moveToStage($run, $nextStage);
                if (in_array($nextStage, ['review', 'scheduled', 'draft'])) {
                    $stateService->markCompleted($run);
                }
            }
            return;
        }

        $stateService->moveToStage($run, 'image_generation');

        try {
            $imageGenerator->generate($article);
            
            if ($nextStage === 'publishing') {
                \App\Jobs\PublishArticle::dispatch($run->id, $article->id);
            } else {
                $stateService->moveToStage($run, $nextStage);
                if (in_array($nextStage, ['review', 'scheduled', 'draft'])) {
                    $stateService->markCompleted($run);
                }
            }
        } catch (\Exception $e) {
            Log::error("GenerateImage failed for article {$article->id}: " . $e->getMessage());

            if ($this->attempts() >= $this->tries) {
                // Non-blocking failure pattern: record failure but continue
                Log::warning("GenerateImage: Retry budget exhausted for article {$article->id}. Continuing without image.");
                
                ArticleImage::updateOrCreate(
                    ['article_id' => $article->id],
                    [
                        'prompt' => 'Failed to generate',
                        'provider' => 'Unknown',
                        'status' => 'failed',
                    ]
                );

                if ($nextStage === 'publishing') {
                    \App\Jobs\PublishArticle::dispatch($run->id, $article->id);
                } else {
                    $stateService->moveToStage($run, $nextStage);
                    if ($nextStage === 'review') {
                        $stateService->markCompleted($run);
                    }
                }
            } else {
                // Rethrow to trigger a queue retry
                throw $e;
            }
        }
    }
}
