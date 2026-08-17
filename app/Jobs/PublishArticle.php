<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\AutomationRun;
use App\Models\Article;
use App\Services\Automation\Publisher;
use App\Services\Automation\AutomationRunStateService;

class PublishArticle implements ShouldQueue
{
    use Queueable;

    public $tries = 3;
    public int $runId;
    public int $articleId;

    public function __construct(int $runId, int $articleId)
    {
        $this->runId = $runId;
        $this->articleId = $articleId;
        $this->tries = config('automation.retry_budget', 3);
    }

    public function handle(AutomationRunStateService $stateService, Publisher $publisher)
    {
        $run = AutomationRun::find($this->runId);
        $article = Article::find($this->articleId);

        if (!$run || !$article) {
            return;
        }

        $stateService->moveToStage($run, 'publishing');

        try {
            $published = $publisher->publish($article);
            
            if ($published) {
                $stateService->markCompleted($run);
            } else {
                // If skipped (e.g. review mode and not approved), it's not a failure, but the run completes for now.
                if ($run->profile->publish_mode === 'draft' || $run->profile->publish_mode === 'review' || $run->profile->publish_mode === 'scheduled') {
                    $stateService->markCompleted($run);
                } else {
                    // For auto_publish it should have published.
                    $stateService->markCompleted($run);
                }
            }
        } catch (\Exception $e) {
            if ($this->attempts() >= $this->tries) {
                $stateService->markFailed($run, $e);
            }
            throw $e;
        }
    }
}
