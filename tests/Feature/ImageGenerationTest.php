<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Article;
use App\Models\BlogTopic;
use App\Models\AutomationProfile;
use App\Jobs\GenerateImage;
use App\Services\AI\ImageProvider;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Log;

class ImageGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_generation_failure_does_not_block_pipeline()
    {
        $article = new Article();
        $article->id = 1;
        $article->title = 'Test Article';
        $article->featured_image_path = null;
        
        $profile = new AutomationProfile();
        $topic = new BlogTopic();
        $article->setRelation('profile', $profile);
        $article->setRelation('topic', $topic);

        $mockImageProvider = Mockery::mock(ImageProvider::class);
        $mockImageProvider->shouldReceive('generate')->andThrow(new AiGenerationException("fal.ai API failed"));

        $mockLlm = Mockery::mock(LlmProvider::class);
        $mockLlm->shouldReceive('generate')->andReturn([
            'success' => true,
            'text' => json_encode(['prompt' => 'A test image'])
        ]);

        $job = new GenerateImage($article);

        // The job should NOT throw an exception, it should catch it and just log it.
        Log::shouldReceive('error')->once()->withArgs(function($message) use ($article) {
            return str_contains($message, "GenerateImage job failed for article {$article->id}");
        });

        $job->handle($mockImageProvider, $mockLlm, new PromptService());

        // Article should just not have an image, but it shouldn't have thrown an error.
        $this->assertNull($article->featured_image_path);
    }
}
