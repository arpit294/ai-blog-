<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunAutomationJob implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    protected int $runId;

    public function __construct(int $runId)
    {
        $this->runId = $runId;
    }

    public function handle(
        \App\Services\Automation\AutomationRunStateService $stateService,
        \App\Services\Automation\QuotaManager $quotaManager,
        \App\Services\Automation\QuotaConsumptionService $quotaConsumptionService,
        \App\Services\Automation\TopicGenerator $topicGenerator,
        \App\Services\Automation\DuplicateDetector $duplicateDetector
    ): void
    {
        $run = \App\Models\AutomationRun::find($this->runId);

        if (!$run) {
            \Illuminate\Support\Facades\Log::warning("RunAutomationJob: AutomationRun {$this->runId} not found.");
            return;
        }

        // Only process if it's queued or failed (from a previous retry)
        if (!in_array($run->status, ['queued', 'failed'])) {
            \Illuminate\Support\Facades\Log::warning("RunAutomationJob: AutomationRun {$this->runId} is in status {$run->status}, skipping.");
            return;
        }

        try {
            $stateService->markRunning($run);
            \Illuminate\Support\Facades\Log::info("RunAutomationJob: Started run {$run->id}");

            // Phase 3: Quota Check
            if (!$quotaManager->canRun($run->profile)) {
                \Illuminate\Support\Facades\Log::info("RunAutomationJob: Quota exhausted for profile {$run->profile->id}, skipping run {$run->id}.");
                $stateService->markSkipped($run, 'quota_exhausted');
                return;
            }

            // Phase 4: Topic Selection
            $stateService->moveToStage($run, 'topic_selection');
            
            $customTopicTitle = $run->metadata['custom_topic'] ?? null;
            if ($customTopicTitle) {
                \Illuminate\Support\Facades\Log::info("RunAutomationJob: Using custom topic for run {$run->id}: {$customTopicTitle}");
                $reservedTopic = \App\Models\BlogTopic::create([
                    'automation_id' => $run->profile->id,
                    'title' => $customTopicTitle,
                    'normalized_title' => \App\Services\Automation\TopicGenerator::normalizeTitle($customTopicTitle),
                    'summary' => 'Custom topic provided manually by user.',
                    'status' => 'reserved',
                    'source_run_id' => $run->id,
                ]);
            } else {
                $reservedTopic = $this->selectUniqueTopic($run, $topicGenerator, $duplicateDetector);
                
                if (!$reservedTopic) {
                    \Illuminate\Support\Facades\Log::info("RunAutomationJob: Batch 1 failed, requesting Batch 2.");
                    $reservedTopic = $this->selectUniqueTopic($run, $topicGenerator, $duplicateDetector);
                    
                    if (!$reservedTopic) {
                        \Illuminate\Support\Facades\Log::warning("RunAutomationJob: No unique topic found after 2 batches.");
                        $stateService->markSkipped($run, 'no_topic');
                        return;
                    }
                }
            }

            \Illuminate\Support\Facades\Log::info("RunAutomationJob: Dispatched GenerateArticle for run {$run->id} and topic {$reservedTopic->id}.");
            \App\Jobs\GenerateArticle::dispatch($run->id, $reservedTopic->id);
            \Illuminate\Support\Facades\Log::info("RunAutomationJob: Completed run {$run->id}");

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("RunAutomationJob: Error in run {$run->id}: " . $e->getMessage());
            $stateService->markFailed($run, $e);
            
            // Re-throw to allow Laravel queue to handle retries
            throw $e;
        }
    }

    protected function selectUniqueTopic(
        \App\Models\AutomationRun $run,
        \App\Services\Automation\TopicGenerator $topicGenerator,
        \App\Services\Automation\DuplicateDetector $duplicateDetector
    ): ?\App\Models\BlogTopic {
        $candidates = $topicGenerator->generate($run->profile, $run);

        foreach ($candidates as $candidate) {
            $decision = $duplicateDetector->decide($run->profile, $candidate);

            if ($decision['decision'] === 'accept') {
                try {
                    $reserved = \Illuminate\Support\Facades\DB::transaction(function () use ($candidate) {
                        $fresh = \App\Models\BlogTopic::where('id', $candidate->id)->lockForUpdate()->first();
                        
                        if ($fresh && $fresh->status === 'candidate') {
                            $fresh->update(['status' => 'reserved']);
                            return $fresh;
                        }
                        return null;
                    });

                    if ($reserved) {
                        return $reserved;
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to reserve topic: " . $e->getMessage());
                }
            } else {
                $candidate->update([
                    'status' => $decision['decision'] === 'review_required' ? 'review_required' : 'rejected',
                    'rejection_reason' => $decision['reason'],
                    'matched_record_type' => $decision['matched_type'] ?? null,
                    'matched_record_id' => $decision['matched_id'] ?? null,
                    'similarity_score' => $decision['score'] ?? null,
                ]);
            }
        }

        return null;
    }
}
