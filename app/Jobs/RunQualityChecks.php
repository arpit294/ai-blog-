<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunQualityChecks implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public $tries;

    protected int $runId;
    protected int $articleId;

    public function __construct(int $runId, int $articleId)
    {
        $this->runId = $runId;
        $this->articleId = $articleId;
        $this->tries = config('automation.retry_budget', 3);
    }

    public function handle(\App\Services\Automation\AutomationRunStateService $stateService, \App\Services\Automation\QualityChecker $qualityChecker, \App\Services\Automation\ContentGenerator $contentGenerator, \App\Services\Automation\SeoGenerator $seoGenerator)
    {
        $run = \App\Models\AutomationRun::find($this->runId);
        $article = \App\Models\Article::find($this->articleId);

        if (!$run || !$article) return;

        $stateService->moveToStage($run, 'quality_check');

        $check = $qualityChecker->runChecks($article, $run->profile);
        $details = $check->details ?? [];

        if (empty($details)) {
            // Passed
            $check->update(['status' => 'passed']);
            $article->update(['status' => 'review']);
            \App\Jobs\GenerateImage::dispatch($this->runId, $this->articleId);
            return;
        }

        // Check if regenerable
        $regenerableKeys = ['structure', 'length', 'repetition', 'seo', 'keyword_use', 'readability'];
        $isRegenerable = false;
        foreach ($details as $k => $v) {
            if (in_array($k, $regenerableKeys)) $isRegenerable = true;
        }

        if ($isRegenerable && $this->attempts() < $this->tries) {
            $check->update(['status' => 'failed']);
            
            // Targeted regeneration
            if (isset($details['structure']) || isset($details['length']) || isset($details['repetition']) || isset($details['readability'])) {
                $contentData = json_decode($article->content, true);
                if (isset($contentData['sections']) && count($contentData['sections']) > 0) {
                    $contentGenerator->regenerateSection($article, $contentData['sections'][0]['heading'], "Fix quality issues: " . json_encode($details));
                }
            } elseif (isset($details['seo']) || isset($details['keyword_use'])) {
                if ($article->seo) $article->seo->delete();
                $seoGenerator->generate($run->profile, $article, $run->id);
            }

            throw new \Exception("Quality check failed. Targeted regeneration executed. Retrying QualityCheck...");
        }

        // Not regenerable or budget exhausted
        $check->update(['status' => 'needs_review']);
        $article->update(['status' => 'needs_review']);
        \App\Jobs\GenerateImage::dispatch($this->runId, $this->articleId);
    }
}
