<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Automation\AutomationDueChecker;
use App\Services\Automation\AutomationRunService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function (AutomationDueChecker $checker, AutomationRunService $runner) {
    $dueProfiles = $checker->getDueProfiles();
    foreach ($dueProfiles as $profile) {
        $runner->dispatchRun($profile);
    }
})->everyMinute()->name('automation-run-dispatcher')->withoutOverlapping();
