<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Automation\TopicGenerator;
use App\Services\Automation\CompetitorAnalyzer;
use App\Services\AI\LlmProvider;
use App\Services\AI\SeoDataProvider;
use App\Models\AutomationProfile;
use App\Models\AutomationRun;
use App\Models\BlogTopic;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TopicGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_generator_includes_seo_and_competitor_data_in_prompt()
    {
        $user = \App\Models\User::factory()->create();
        
        // Bypass eloquent requirements and insert directly for test speed
        $profileId = \Illuminate\Support\Facades\DB::table('automation_profiles')->insertGetId([
            'user_id' => $user->id,
            'name' => 'Test',
            'niche' => 'Test Niche',
            'target_audience' => 'Test',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 500,
            'max_words' => 1000,
            'quota_count' => 0,
            'quota_period' => 'monthly',
            'competitor_urls' => json_encode(['https://example.com']),
        ]);

        $runId = \Illuminate\Support\Facades\DB::table('automation_runs')->insertGetId([
            'automation_profile_id' => $profileId,
            'run_key' => 'test_run_' . uniqid(),
            'status' => 'running'
        ]);

        $profile = AutomationProfile::find($profileId);
        $run = AutomationRun::find($runId);

        $mockSeo = Mockery::mock(SeoDataProvider::class);
        $mockSeo->shouldReceive('fetchKeywords')->andReturn([
            ['keyword' => 'test keyword 1', 'search_volume' => 1000],
            ['keyword' => 'test keyword 2', 'search_volume' => 500],
        ]);

        $mockCompetitor = Mockery::mock(CompetitorAnalyzer::class);
        $mockCompetitor->shouldReceive('fetchSitemap')->andReturn(['https://example.com/article1']);
        $mockCompetitor->shouldReceive('extractTopics')->andReturn(['Example Article 1 Title']);

        $mockLlm = Mockery::mock(LlmProvider::class);
        $mockLlm->shouldReceive('generate')->withArgs(function($model, $prompt) {
            $this->assertStringContainsString('test keyword 1 (Vol: 1000)', $prompt);
            $this->assertStringContainsString('Example Article 1 Title', $prompt);
            return true;
        })->andReturn([
            'success' => true,
            'text' => json_encode([
                'topics' => [
                    [
                        'title' => 'Topic 1',
                        'summary' => 'Summary 1',
                        'category' => 'Cat 1',
                        'intent' => 'info',
                        'primary_keyword' => 'kw1',
                        'target_keyword' => 'test keyword 1',
                        'estimated_search_volume' => 1000
                    ]
                ]
            ])
        ]);

        $generator = new TopicGenerator($mockLlm, $mockSeo, $mockCompetitor);
        $topics = $generator->generate($profile, $run);

        $this->assertCount(1, $topics);
        $this->assertEquals('Topic 1', $topics[0]->title);
        $this->assertEquals('test keyword 1', $topics[0]->target_keyword);
        $this->assertEquals(1000, $topics[0]->estimated_search_volume);
    }
}
