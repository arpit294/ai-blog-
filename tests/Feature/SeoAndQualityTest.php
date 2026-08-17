<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\AutomationRun;
use App\Models\Article;
use App\Models\ArticleSeo;
use App\Models\QualityCheck;
use App\Services\AI\LlmProvider;
use App\Services\Automation\SeoGenerator;
use App\Services\Automation\QualityChecker;
use App\Jobs\RunQualityChecks;
use App\Jobs\GenerateSeo;

class SeoAndQualityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $profile;
    protected $topic;
    protected $run;
    protected $article;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $this->profile = AutomationProfile::create([
            'user_id' => $this->user->id,
            'name' => 'SEO Test Profile',
            'niche' => 'Tech',
            'target_audience' => 'Devs',
            'language' => 'English',
            'tone' => 'Professional',
            'min_words' => 10,
            'max_words' => 1000,
            'duplicate_threshold' => 0.85,
            'semantic_duplicate_threshold' => 0.85,
            'duplicate_mode' => 'strict',
            'quota_count' => 10,
            'quota_period' => 'monthly',
        ]);

        $this->topic = BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => 'Test Topic',
            'normalized_title' => 'test topic',
            'summary' => 'Summary',
            'status' => 'used',
        ]);

        $this->run = AutomationRun::create([
            'automation_profile_id' => $this->profile->id,
            'run_key' => 'test_run',
            'status' => 'running',
            'current_stage' => 'content_generation',
            'attempts' => 1,
        ]);

        $this->article = Article::create([
            'user_id' => $this->user->id,
            'automation_profile_id' => $this->profile->id,
            'topic_id' => $this->topic->id,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'content' => json_encode([
                'introduction' => 'Intro',
                'sections' => [['heading' => 'H1', 'content' => 'Content here and there to have some length']],
                'conclusion' => 'Conclusion'
            ]),
            'status' => 'content_generated'
        ]);
    }

    public function test_seo_generator_produces_metadata()
    {
        $mockLlm = $this->createMock(LlmProvider::class);
        $this->app->instance(LlmProvider::class, $mockLlm);

        $mockLlm->expects($this->once())
            ->method('generate')
            ->willReturn([
                'text' => json_encode([
                    'seo_title' => 'Seo Title',
                    'meta_description' => 'Meta description',
                    'focus_keyword' => 'keyword',
                ])
            ]);

        $seoGen = app(SeoGenerator::class);
        $seo = $seoGen->generate($this->profile, $this->article, $this->run->id);

        $this->assertInstanceOf(ArticleSeo::class, $seo);
        $this->assertEquals('Seo Title', $seo->seo_title);
        $this->assertEquals('keyword', $seo->focus_keyword);
    }

    public function test_quality_checker_computes_score()
    {
        // Add fake SEO
        ArticleSeo::create([
            'article_id' => $this->article->id,
            'seo_title' => 'Seo Title',
            'meta_description' => 'Desc'
        ]);

        $mockEmbedding = $this->createMock(\App\Services\AI\EmbeddingProvider::class);
        $mockEmbedding->method('embed')->willReturn(array_fill(0, 768, 0.1));
        $this->app->instance(\App\Services\AI\EmbeddingProvider::class, $mockEmbedding);

        $checker = app(QualityChecker::class);
        $result = $checker->runChecks($this->article, $this->profile);

        $this->assertInstanceOf(QualityCheck::class, $result);
        
        // Structure: intro(0.3) + sections(0.4) + conclusion(0.3) = 1.0
        // Length: text length is within bounds = 1.0
        // Seo: title(0.5) + desc(0.5) = 1.0
        // Repetition: 1.0
        // Keyword Use: 1.0
        // Code Blocks: 1.0
        // Uniqueness: 1.0 (no past content)
        // Readability: 1.0
        
        $this->assertEquals(1.0, $result->structure_score);
        $this->assertEquals(1.0, $result->overall_score);
    }

    public function test_run_quality_checks_job_triggers_regeneration_on_failure()
    {
        $mockEmbedding = $this->createMock(\App\Services\AI\EmbeddingProvider::class);
        $mockEmbedding->method('embed')->willReturn(array_fill(0, 768, 0.1));
        $this->app->instance(\App\Services\AI\EmbeddingProvider::class, $mockEmbedding);

        $mockLlm = $this->createMock(\App\Services\AI\LlmProvider::class);
        $this->app->instance(\App\Services\AI\LlmProvider::class, $mockLlm);

        // Missing SEO triggers SEO failure
        $checker = app(QualityChecker::class);
        $result = $checker->runChecks($this->article, $this->profile);
        $this->assertArrayHasKey('seo', $result->details);

        // Run job -> should dispatch regeneration and throw exception to retry
        $mockSeoGen = $this->createMock(SeoGenerator::class);
        $mockSeoGen->expects($this->once())->method('generate');
        $this->app->instance(SeoGenerator::class, $mockSeoGen);

        $job = new RunQualityChecks($this->run->id, $this->article->id);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Quality check failed');
        $job->handle(
            app(\App\Services\Automation\AutomationRunStateService::class),
            app(QualityChecker::class),
            app(\App\Services\Automation\ContentGenerator::class),
            $mockSeoGen
        );
    }

    public function test_review_actions_update_status()
    {
        $this->actingAs($this->user);

        $response = $this->patch(route('articles.approve', $this->article));
        $response->assertSessionHas('success');
        $this->assertEquals('approved', $this->article->fresh()->status);

        $response = $this->patch(route('articles.reject', $this->article));
        $response->assertSessionHas('success');
        $this->assertEquals('rejected', $this->article->fresh()->status);
    }
}
