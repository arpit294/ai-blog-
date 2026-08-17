<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\AutomationRun;
use App\Models\BlogTopic;
use App\Services\Automation\TopicGenerator;
use App\Services\Automation\DuplicateDetector;
use App\Services\AI\LlmProvider;

class TopicMemoryTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $profile;
    protected $run;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $this->profile = AutomationProfile::create([
            'user_id' => $this->user->id,
            'name' => 'Tech Blog',
            'niche' => 'Tech',
            'target_audience' => 'Devs',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 500,
            'max_words' => 1000,
            'quota_count' => 10,
            'quota_period' => 'monthly',
            'duplicate_threshold' => 0.80,
        ]);
        
        $this->run = AutomationRun::create([
            'automation_profile_id' => $this->profile->id,
            'status' => 'queued',
            'current_stage' => 'queued',
            'run_key' => 'test_run_1',
        ]);
    }

    public function test_title_normalization()
    {
        $title1 = "What is Laravel? (A Complete Guide)";
        $normalized1 = TopicGenerator::normalizeTitle($title1);
        $this->assertEquals("what is laravel a complete guide", $normalized1);
    }

    public function test_check_exact_duplicate()
    {
        BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => "What is Laravel?",
            'normalized_title' => "what is laravel",
            'summary' => "summary",
            'status' => 'reserved',
        ]);

        $detector = app(DuplicateDetector::class);
        
        // Exact match
        $this->assertTrue($detector->checkExact($this->profile, "what is laravel"));
        // Near exact with punctuation
        $this->assertTrue($detector->checkExact($this->profile, "What is Laravel?!"));
        // Different
        $this->assertFalse($detector->checkExact($this->profile, "What is PHP"));
    }

    public function test_check_text_similarity()
    {
        BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => "How to learn Laravel in 2026",
            'normalized_title' => TopicGenerator::normalizeTitle("How to learn Laravel in 2026"),
            'summary' => "summary",
            'status' => 'used',
        ]);

        $detector = app(DuplicateDetector::class);
        
        $candidateTitle = "How to learn Laravel framework in 2026";
        $score = $detector->checkTextSimilarity($this->profile, $candidateTitle, "");

        $this->assertGreaterThan(0.80, $score);
        
        $decision = $detector->decide($this->profile, new BlogTopic(['title' => $candidateTitle, 'summary' => '']));
        $this->assertEquals('reject', $decision['decision']);
        $this->assertEquals('text_duplicate', $decision['reason']);
    }

    public function test_no_topic_found_stops_job_and_does_not_consume_quota()
    {
        // Mock LlmProvider to return duplicates
        $mockLlm = $this->createMock(LlmProvider::class);
        $mockLlm->method('generate')->willReturn([
            'success' => true,
            'text' => json_encode([
                ['title' => 'Existing Topic', 'summary' => 'summary', 'category' => 'Tech', 'intent' => 'info', 'primary_keyword' => 'tech']
            ]),
            'model' => 'test',
            'duration_ms' => 100,
            'usage' => []
        ]);
        $this->app->instance(LlmProvider::class, $mockLlm);

        // Pre-create the duplicate
        BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => 'Existing Topic',
            'normalized_title' => TopicGenerator::normalizeTitle('Existing Topic'),
            'summary' => 'summary',
            'status' => 'used',
        ]);

        $job = new \App\Jobs\RunAutomationJob($this->run->id);
        $job->handle(
            app(\App\Services\Automation\AutomationRunStateService::class),
            app(\App\Services\Automation\QuotaManager::class),
            app(\App\Services\Automation\QuotaConsumptionService::class),
            app(TopicGenerator::class),
            app(DuplicateDetector::class)
        );

        $this->run->refresh();
        $this->assertEquals('skipped', $this->run->status);
        $this->assertEquals('no_topic', $this->run->last_error);
    }
}
