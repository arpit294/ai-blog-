<?php

namespace Tests\Feature;

use App\Models\AutomationProfile;
use App\Models\AutomationRun;
use App\Models\User;
use App\Services\Automation\AutomationDueChecker;
use App\Services\Automation\AutomationRunService;
use App\Services\Automation\ScheduleCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use App\Jobs\RunAutomationJob;

class AutomationExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function createActiveProfile(User $user)
    {
        return AutomationProfile::create([
            'user_id' => $user->id,
            'name' => 'Active Profile',
            'niche' => 'Tech',
            'target_audience' => 'Devs',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 1000,
            'max_words' => 2000,
            'quota_count' => 1,
            'quota_period' => 'daily',
            'publish_mode' => 'draft',
            'status' => 'active',
            'next_run_at' => now()->subMinutes(5), // Due
        ]);
    }

    public function test_due_checker_detects_due_active_profiles()
    {
        $user = User::factory()->create();
        $profile = $this->createActiveProfile($user);

        $checker = app(AutomationDueChecker::class);
        $due = $checker->getDueProfiles();

        $this->assertCount(1, $due);
        $this->assertEquals($profile->id, $due->first()->id);
    }

    public function test_disabled_profile_does_not_create_run()
    {
        $user = User::factory()->create();
        $profile = $this->createActiveProfile($user);
        $profile->update(['status' => 'disabled']);

        $checker = app(AutomationDueChecker::class);
        $due = $checker->getDueProfiles();

        $this->assertCount(0, $due);
    }

    public function test_automation_run_service_creates_run_and_dispatches_job()
    {
        $user = User::factory()->create();
        $profile = $this->createActiveProfile($user);

        $service = app(AutomationRunService::class);
        $run = $service->dispatchRun($profile);

        $this->assertNotNull($run);
        $this->assertEquals('queued', $run->status);
        $this->assertEquals('scheduler', $run->current_stage);
        
        Queue::assertPushed(RunAutomationJob::class);
    }

    public function test_duplicate_scheduler_execution_does_not_create_duplicate_run()
    {
        $user = User::factory()->create();
        $profile = $this->createActiveProfile($user);
        
        $profile->next_run_at = now()->startOfDay();
        $profile->save();

        $service = app(AutomationRunService::class);
        
        // Dispatch first time
        $run1 = $service->dispatchRun($profile);
        
        // Manually reset the next_run_at to simulate same time execution/concurrency bypass
        $profile->next_run_at = now()->startOfDay();
        $profile->save();

        // Second dispatch should return null due to deterministic run key constraint
        $run2 = $service->dispatchRun($profile);

        $this->assertNull($run2);
        $this->assertEquals(1, AutomationRun::count());
    }

    public function test_schedule_calculator_determines_next_valid_time()
    {
        $user = User::factory()->create();
        $profile = $this->createActiveProfile($user);
        $profile->schedules()->create([
            'weekday' => 'Monday',
            'time' => '10:00:00'
        ]);

        $calculator = app(ScheduleCalculator::class);
        // Pretend now is a Sunday
        $next = $calculator->calculateNextRun($profile, now()->startOfWeek()->subDay());

        $this->assertEquals('Monday', $next->format('l'));
        $this->assertEquals('10:00:00', $next->format('H:i:s'));
    }
}
