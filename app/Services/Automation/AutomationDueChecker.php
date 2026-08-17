<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use Illuminate\Database\Eloquent\Collection;

class AutomationDueChecker
{
    public function getDueProfiles(): Collection
    {
        return AutomationProfile::where('status', 'active')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();
    }
}
