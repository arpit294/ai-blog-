<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\Article;
use App\Models\AutomationEmbedding;
use App\Services\AI\EmbeddingProvider;
use App\Services\Automation\DuplicateDetector;
use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Log;

class TopicMemorySemanticTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $profile;
    protected $mockEmbeddingService;

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
            'duplicate_threshold' => 0.85,
            'semantic_duplicate_threshold' => 0.85,
            'duplicate_mode' => 'strict',
            'quota_count' => 10,
            'quota_period' => 'monthly',
        ]);
        
        $this->mockEmbeddingService = $this->createMock(EmbeddingProvider::class);
        $this->app->instance(EmbeddingProvider::class, $this->mockEmbeddingService);
    }

    public function test_short_circuit_does_not_call_embeddings()
    {
        BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => 'Laravel Queues',
            'normalized_title' => 'laravel queues',
            'summary' => 'summary',
            'status' => 'reserved',
        ]);

        // Mock shouldn't be called because exact text match short-circuits
        $this->mockEmbeddingService->expects($this->never())->method('embed');
        
        $detector = app(DuplicateDetector::class);
        $decision = $detector->decide($this->profile, new BlogTopic(['title' => 'Laravel Queues', 'summary' => 'summary']));
        
        $this->assertEquals('reject', $decision['decision']);
        $this->assertEquals('exact_duplicate', $decision['reason']);
    }

    public function test_semantic_rejection_stores_matched_record()
    {
        $existingTopic = BlogTopic::create([
            'automation_id' => $this->profile->id,
            'title' => 'Understanding Laravel Queues',
            'normalized_title' => 'understanding laravel queues',
            'summary' => 'Deep dive into queues',
            'status' => 'reserved',
        ]);
        
        AutomationEmbedding::create([
            'embeddable_type' => BlogTopic::class,
            'embeddable_id' => $existingTopic->id,
            'vector' => [1.0, 0.0, 0.0],
            'model_name' => 'test',
            'dimensions' => 3
        ]);

        $this->mockEmbeddingService->method('embed')->willReturn([0.9, 0.1, 0.0]); // Cosine sim will be very high (0.9 / sqrt(0.82) ~ 0.99)

        $detector = app(DuplicateDetector::class);
        
        // Use a title that bypasses exact/text
        $candidate = new BlogTopic(['title' => 'Laravel Queue Tutorial', 'summary' => 'How to use queues']);
        $decision = $detector->decide($this->profile, $candidate);

        $this->assertEquals('reject', $decision['decision']);
        $this->assertEquals('semantic_duplicate', $decision['reason']);
        $this->assertGreaterThan(0.9, $decision['score']);
        $this->assertEquals(BlogTopic::class, $decision['matched_type']);
        $this->assertEquals($existingTopic->id, $decision['matched_id']);
    }

    public function test_strict_mode_handles_embedding_failure_by_routing_to_review()
    {
        $this->mockEmbeddingService->method('embed')->willThrowException(new AiGenerationException("Ollama down"));
        
        $detector = app(DuplicateDetector::class);
        
        $candidate = new BlogTopic(['title' => 'New Topic', 'summary' => 'Summary']);
        $decision = $detector->decide($this->profile, $candidate);

        $this->assertEquals('review_required', $decision['decision']);
        $this->assertEquals('semantic_check_failed', $decision['reason']);
    }
}
