<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\AutomationQuotaEvent;
use Carbon\Carbon;

class QuotaManager
{
    protected QuotaWindowCalculator $windowCalculator;

    public function __construct(QuotaWindowCalculator $windowCalculator)
    {
        $this->windowCalculator = $windowCalculator;
    }

    public function canRun(AutomationProfile $profile, Carbon $now = null): bool
    {
        $status = $this->getQuotaStatus($profile, $now);
        return !$status['exhausted'];
    }

    public function getQuotaStatus(AutomationProfile $profile, Carbon $now = null): array
    {
        $window = $this->windowCalculator->calculateWindow($profile->quota_period, $profile->timezone, $now);
        
        $used = AutomationQuotaEvent::where('automation_profile_id', $profile->id)
            ->where('completion_type', $profile->completion_state)
            ->where('quota_window_start', '>=', $window['start'])
            ->where('quota_window_end', '<=', $window['end'])
            ->count();

        if ($profile->quota_mode === 'unlimited') {
            return [
                'mode' => 'unlimited',
                'limit' => null,
                'used' => $used,
                'remaining' => null,
                'period' => $window['period'],
                'window_start' => $window['start']->toDateTimeString(),
                'window_end' => $window['end']->toDateTimeString(),
                'percentage' => 0,
                'exhausted' => false,
                'timezone' => $profile->timezone,
            ];
        }

        $limit = $profile->quota_count;
        $remaining = max(0, $limit - $used);
        $exhausted = $used >= $limit;
        $percentage = $limit > 0 ? min(100, round(($used / $limit) * 100, 2)) : 100;

        return [
            'mode' => 'limited',
            'limit' => $limit,
            'used' => $used,
            'remaining' => $remaining,
            'period' => $window['period'],
            'window_start' => $window['start']->toDateTimeString(),
            'window_end' => $window['end']->toDateTimeString(),
            'percentage' => $percentage,
            'exhausted' => $exhausted,
            'timezone' => $profile->timezone,
        ];
    }
}
