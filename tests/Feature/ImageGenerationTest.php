<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\AutomationRun;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Jobs\GenerateImage;
use App\Services\AI\ImageProvider;
use App\Services\AI\ImageResult;
use App\Services\AI\LlmProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationTest extends TestCase
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
        Storage::fake('public');

        $this->user = User::factory()->create();
        
        $this->profile = AutomationProfile::create([
            'user_id' => $this->user->id,
            'name' => 'Image Test Profile',
            'generate_image' => true,
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
            'current_stage' => 'quality_check',
            'attempts' => 1,
        ]);

        $this->article = Article::create([
            'user_id' => $this->user->id,
            'automation_profile_id' => $this->profile->id,
            'topic_id' => $this->topic->id,
            'title' => 'Test Topic',
            'slug' => 'test-topic',
            'content' => '{}',
            'status' => 'review'
        ]);
    }

    public function test_job_skipped_if_profile_disabled()
    {
        $this->profile->update(['generate_image' => false]);
        
        $job = new GenerateImage($this->run->id, $this->article->id);
        $job->handle(app(\App\Services\Automation\AutomationRunStateService::class), app(\App\Services\Automation\ImageGenerator::class));

        $this->assertDatabaseCount('article_images', 0);
        $this->assertEquals('completed', $this->run->fresh()->current_stage);
    }

    public function test_image_provider_contract_succeeds()
    {
        // Mock LLM for prompts
        $mockLlm = $this->createMock(LlmProvider::class);
        $mockLlm->method('generate')->willReturn([
            'text' => json_encode(['prompt' => 'A test prompt', 'alt_text' => 'A test alt text'])
        ]);
        $this->app->instance(LlmProvider::class, $mockLlm);

        // Mock Image Provider
        $mockImage = $this->createMock(ImageProvider::class);
        // Provide a valid JPEG byte stream for Intervention to parse (a tiny 1x1 jpeg)
        $validJpeg = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $mockImage->expects($this->once())->method('generate')->willReturn(new ImageResult('gen-123', 1024, 1024, $validJpeg));
        $this->app->instance(ImageProvider::class, $mockImage);

        $job = new GenerateImage($this->run->id, $this->article->id);
        $job->handle(app(\App\Services\Automation\AutomationRunStateService::class), app(\App\Services\Automation\ImageGenerator::class));

        $this->assertDatabaseCount('article_images', 1);
        $image = ArticleImage::first();
        $this->assertEquals('A test prompt', $image->prompt);
        $this->assertEquals('A test alt text', $image->alt_text);
        $this->assertEquals('gen-123', $image->provider_generation_id);
        $this->assertEquals('generated', $image->status);

        $this->assertEquals('completed', $this->run->fresh()->current_stage);
        
        // Assert storage
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_failure_never_blocks_run()
    {
        // Mock LLM
        $mockLlm = $this->createMock(LlmProvider::class);
        $mockLlm->method('generate')->willReturn([
            'text' => json_encode(['prompt' => 'A test prompt', 'alt_text' => 'A test alt text'])
        ]);
        $this->app->instance(LlmProvider::class, $mockLlm);

        // Mock Image Provider to fail
        $mockImage = $this->createMock(ImageProvider::class);
        $mockImage->method('generate')->willThrowException(new \Exception('API Error'));
        $this->app->instance(ImageProvider::class, $mockImage);

        $job = new GenerateImage($this->run->id, $this->article->id);
        
        // Force attempts to exceed tries to simulate exhausted budget
        $job->tries = 1;
        
        // This should NOT throw an exception because attempts >= tries
        $job->handle(app(\App\Services\Automation\AutomationRunStateService::class), app(\App\Services\Automation\ImageGenerator::class));

        // State should still advance to completed
        $this->assertEquals('completed', $this->run->fresh()->current_stage);
        
        // Image should be logged as failed
        $this->assertDatabaseCount('article_images', 1);
        $this->assertEquals('failed', ArticleImage::first()->status);
    }

    public function test_manual_regenerate_action()
    {
        $this->actingAs($this->user);
        
        // Insert existing failed image
        ArticleImage::create([
            'article_id' => $this->article->id,
            'path' => 'old.jpg',
            'prompt' => 'old',
            'provider' => 'old',
            'status' => 'failed'
        ]);

        $response = $this->patch(route('articles.regenerateImage', $this->article));
        $response->assertSessionHas('success');
        
        // Since we dispatchSync in the controller, it will hit the container. 
        // Note: in a real environment it would queue, but here it runs synchronously.
        // We'll just assert it returns 302 and redirects back.
        $response->assertStatus(302);
    }
}
