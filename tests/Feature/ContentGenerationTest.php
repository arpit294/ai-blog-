<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\AutomationRun;
use App\Models\Article;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use App\Services\Automation\ContentGenerator;
use App\Services\Automation\AutomationRunStateService;
use App\Jobs\GenerateArticle;
use App\Jobs\GenerateSeo;
use Illuminate\Support\Facades\Queue;

class ContentGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $profile;
    protected $topic;
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
            'status' => 'reserved',
        ]);

        $this->run = AutomationRun::create([
            'automation_profile_id' => $this->profile->id,
            'run_key' => 'test_run',
            'status' => 'running',
            'current_stage' => 'content_generation',
            'attempts' => 1,
        ]);
    }

    public function test_prompt_service_composes_correctly()
    {
        $promptService = new PromptService();
        $prompt = $promptService->buildPrompt('brief', $this->profile, $this->topic);

        $this->assertStringContainsString('Niche: Tech', $prompt);
        $this->assertStringContainsString('Topic Title: Test Topic', $prompt);
        $this->assertStringContainsString('Generate a creative brief', $prompt);
    }

    public function test_content_generator_validates_structure_and_enforces_word_count()
    {
        $mockLlm = $this->createMock(LlmProvider::class);
        $this->app->instance(LlmProvider::class, $mockLlm);

        $mockLlm->expects($this->exactly(6))
                ->method('generate')
                ->willReturnOnConsecutiveCalls(
                    ['text' => '{"brief": "test brief"}'], // brief
                    ['text' => '{"headings": ["H1"]}'], // outline
                    ['text' => '{"content": "short"}'], // section
                    ['text' => json_encode(['title' => 'Test', 'slug' => 'test', 'sections' => [['heading' => 'H1', 'content' => 'short']]])], // assembly
                    ['text' => json_encode(['title' => 'Test', 'slug' => 'test', 'sections' => [['heading' => 'H1', 'content' => 'short']]])], // consistency
                    ['text' => '{"content": "this is a much longer content block with more than ten words total"}'] // regenerate section
                );

        $generator = app(ContentGenerator::class);
        $article = $generator->generate($this->profile, $this->topic, $this->run->id);

        $this->assertEquals('Test', $article['title']);
        $this->assertCount(1, $article['sections']);
        $this->assertEquals('this is a much longer content block with more than ten words total', $article['sections'][0]['content']);
    }

    public function test_content_generator_retries_on_invalid_json()
    {
        $mockLlm = $this->createMock(LlmProvider::class);
        $this->app->instance(LlmProvider::class, $mockLlm);

        // Turn off min_words to avoid extra regenerate call
        $this->profile->update(['min_words' => 0]);

        $mockLlm->expects($this->exactly(6))
                ->method('generate')
                ->willReturnOnConsecutiveCalls(
                    ['text' => 'bad json'], // brief fail
                    ['text' => '{"brief": "test brief"}'], // brief retry success
                    ['text' => '{"headings": ["H1"]}'], // outline
                    ['text' => '{"content": "short"}'], // section
                    ['text' => json_encode(['title' => 'Test', 'slug' => 'test', 'sections' => [['heading' => 'H1', 'content' => 'short']]])], // assembly
                    ['text' => json_encode(['title' => 'Test', 'slug' => 'test', 'sections' => [['heading' => 'H1', 'content' => 'short']]])] // consistency
                );

        $generator = app(ContentGenerator::class);
        $article = $generator->generate($this->profile, $this->topic, $this->run->id);

        $this->assertEquals('Test', $article['title']);
    }

    public function test_generate_article_job_persists_article()
    {
        Queue::fake();

        $mockGenerator = $this->createMock(ContentGenerator::class);
        $this->app->instance(ContentGenerator::class, $mockGenerator);
        
        $mockGenerator->method('generate')->willReturn([
            'title' => 'Job Title',
            'slug' => 'job-slug',
            'sections' => [['heading' => 'H1', 'content' => 'content']]
        ]);

        $job = new GenerateArticle($this->run->id, $this->topic->id);
        $job->handle($mockGenerator, app(AutomationRunStateService::class));

        $this->assertDatabaseHas('articles', [
            'topic_id' => $this->topic->id,
            'title' => 'Job Title',
            'status' => 'content_generated'
        ]);

        $this->topic->refresh();
        $this->assertEquals('used', $this->topic->status);

        Queue::assertPushed(GenerateSeo::class);
    }
}
