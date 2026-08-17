<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use Carbon\Carbon;

class ScheduleCalculator
{
    public function calculateNextRun(AutomationProfile $profile, Carbon $now = null): ?Carbon
    {
        $now = $now ?? Carbon::now();
        $schedules = $profile->schedules;

        if ($schedules->isEmpty()) {
            return null;
        }

        $candidateTimes = [];

        foreach ($schedules as $schedule) {
            $weekday = $schedule->weekday; // e.g. 'Monday'
            $time = $schedule->time;       // e.g. '10:00:00'

            // Create a candidate for this weekday in the current week
            $candidate = $now->copy()->startOfDay()->modify($weekday)->setTimeFromTimeString($time);
            
            // If the candidate is strictly in the past or exactly now, we want the next week's occurrence
            if ($candidate->lessThanOrEqualTo($now)) {
                $candidate->addWeek();
            }

            $candidateTimes[] = $candidate;
        }

        if (empty($candidateTimes)) {
            return null;
        }

        usort($candidateTimes, function ($a, $b) {
            return $a->timestamp <=> $b->timestamp;
        });

        return $candidateTimes[0];
    }
}
