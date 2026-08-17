<?php

namespace App\Services\Automation;

use App\Models\AutomationRun;
use App\Models\AutomationQuotaEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotaConsumptionService
{
    protected QuotaWindowCalculator $windowCalculator;
    protected QuotaManager $quotaManager;

    public function __construct(QuotaWindowCalculator $windowCalculator, QuotaManager $quotaManager)
    {
        $this->windowCalculator = $windowCalculator;
        $this->quotaManager = $quotaManager;
    }

    public function consume(AutomationRun $run, string $completionType): bool
    {
        $profile = $run->profile;

        // Ensure we only consume if this matches the profile's configured completion state.
        if ($completionType !== $profile->completion_state) {
            return false;
        }

        $window = $this->windowCalculator->calculateWindow($profile->quota_period, $profile->timezone);
        $idempotencyKey = "{$profile->id}-{$run->id}-{$completionType}";

        try {
            return DB::transaction(function () use ($profile, $run, $completionType, $window, $idempotencyKey) {
                // Lock the profile row to prevent concurrent consumption from exceeding quota
                DB::table('automation_profiles')->where('id', $profile->id)->lockForUpdate()->first();

                // Idempotency check: did we already consume for this exact run/event?
                $exists = AutomationQuotaEvent::where('idempotency_key', $idempotencyKey)->exists();
                if ($exists) {
                    Log::info("Duplicate quota consumption prevented for key: {$idempotencyKey}");
                    return true;
                }

                // Enforce limit unless unlimited
                if ($profile->quota_mode !== 'unlimited') {
                    $used = AutomationQuotaEvent::where('automation_profile_id', $profile->id)
                        ->where('completion_type', $completionType)
                        ->where('quota_window_start', '>=', $window['start'])
                        ->where('quota_window_end', '<=', $window['end'])
                        ->count();

                    if ($used >= $profile->quota_count) {
                        Log::warning("Quota overrun prevented for profile: {$profile->id}");
                        return false;
                    }
                }

                // Create event
                AutomationQuotaEvent::create([
                    'automation_profile_id' => $profile->id,
                    'automation_run_id' => $run->id,
                    'completion_type' => $completionType,
                    'quota_window_start' => $window['start'],
                    'quota_window_end' => $window['end'],
                    'counted_at' => now(),
                    'idempotency_key' => $idempotencyKey,
                ]);

                Log::info("Quota consumed for profile {$profile->id}, run {$run->id}");
                return true;
            });
        } catch (\Exception $e) {
            // Usually duplicate entry constraint
            Log::error("Quota consumption conflict/failure: " . $e->getMessage());
            return false;
        }
    }
}
