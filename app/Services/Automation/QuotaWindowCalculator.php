<?php

namespace App\Services\Automation;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

class QuotaWindowCalculator
{
    /**
     * Calculates the quota window based on the profile's period and timezone.
     *
     * @param string $period 'daily', 'weekly', 'monthly', 'custom'
     * @param string $timezone
     * @param Carbon|null $now
     * @return array
     */
    public function calculateWindow(string $period, string $timezone, Carbon $now = null): array
    {
        $nowImmutable = $now ? CarbonImmutable::instance($now)->setTimezone($timezone) : CarbonImmutable::now($timezone);

        switch ($period) {
            case 'daily':
                $start = $nowImmutable->startOfDay();
                $end = $nowImmutable->endOfDay();
                break;
            case 'weekly':
                $start = $nowImmutable->startOfWeek();
                $end = $nowImmutable->endOfWeek();
                break;
            case 'monthly':
            case 'custom':
                $start = $nowImmutable->startOfMonth();
                $end = $nowImmutable->endOfMonth();
                break;
            default:
                throw new InvalidArgumentException("Invalid quota period: {$period}");
        }

        return [
            'period' => $period === 'custom' ? 'monthly' : $period,
            'timezone' => $timezone,
            'start' => $start,
            'end' => $end,
        ];
    }
}
