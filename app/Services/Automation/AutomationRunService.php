<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\AutomationRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Jobs\RunAutomationJob;

class AutomationRunService
{
    protected ScheduleCalculator $scheduleCalculator;

    public function __construct(ScheduleCalculator $scheduleCalculator)
    {
        $this->scheduleCalculator = $scheduleCalculator;
    }

    public function dispatchRun(AutomationProfile $profile, ?string $customTopic = null): ?AutomationRun
    {
        $nextRunTime = $profile->next_run_at ? $profile->next_run_at->timestamp : now()->timestamp;
        $runKey = "run_profile_{$profile->id}_time_{$nextRunTime}";

        // Use cache lock to prevent concurrent overlapping scheduler executions
        $lock = Cache::lock("automation_dispatch_{$profile->id}", 10);

        if (!$lock->get()) {
            return null;
        }

        try {
            return DB::transaction(function () use ($profile, $runKey, $customTopic) {
                $existingRun = AutomationRun::where('run_key', $runKey)->first();

                if ($existingRun) {
                    $this->updateProfileTimestamps($profile);
                    return null; 
                }

                $metadata = [];
                if ($customTopic) {
                    $metadata['custom_topic'] = $customTopic;
                }

                $run = AutomationRun::create([
                    'automation_profile_id' => $profile->id,
                    'status' => 'queued',
                    'current_stage' => 'scheduler',
                    'run_key' => $runKey,
                    'metadata' => $metadata,
                ]);

                $this->updateProfileTimestamps($profile);

                // Dispatch the queue job
                RunAutomationJob::dispatch($run->id);

                return $run;
            });
        } finally {
            $lock->release();
        }
    }

    protected function updateProfileTimestamps(AutomationProfile $profile): void
    {
        $profile->last_run_at = now();
        $next = $this->scheduleCalculator->calculateNextRun($profile, now());
        $profile->next_run_at = $next;
        $profile->save();
    }
}
