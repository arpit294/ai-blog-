<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Services\Automation\ContentGenerator;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AssemblyBugTest extends TestCase
{
    use RefreshDatabase;

    public function test_programmatic_assembly_does_not_mutate_or_duplicate_sections()
    {
        $profile = new AutomationProfile();
        $profile->min_words = 0;
        $profile->max_words = 0;

        $topic = new BlogTopic();
        $topic->title = 'Test Topic';
        $topic->summary = 'A test summary';

        $mockLlm = Mockery::mock(LlmProvider::class);
        
        // Mock responses for each stage
        $mockLlm->shouldReceive('generate')->andReturnUsing(function ($model, $prompt) {
            if (str_contains($prompt, 'creative brief')) {
                return ['success' => true, 'text' => json_encode(['brief' => 'Mock Brief'])];
            }
            if (str_contains($prompt, 'detailed outline')) {
                return ['success' => true, 'text' => json_encode(['headings' => ['Section A', 'Section B']])];
            }
            if (str_contains($prompt, "specific section: 'Section A'")) {
                return ['success' => true, 'text' => json_encode(['content' => 'Content for A'])];
            }
            if (str_contains($prompt, "specific section: 'Section B'")) {
                return ['success' => true, 'text' => json_encode(['content' => 'Content for B'])];
            }
            if (str_contains($prompt, 'compelling, engaging introduction')) {
                return ['success' => true, 'text' => json_encode(['introduction' => 'Intro text'])];
            }
            if (str_contains($prompt, 'strong, summarizing conclusion')) {
                return ['success' => true, 'text' => json_encode(['conclusion' => 'Conclusion text'])];
            }
            return ['success' => true, 'text' => '{}'];
        });

        $mockPrompt = new PromptService();
        $mockResearch = Mockery::mock(\App\Services\Automation\ResearchService::class);
        $mockResearch->shouldReceive('research')->andReturn([]);
        $generator = Mockery::mock(ContentGenerator::class . '[logGeneration,resolveLlm,getModelForProvider]', [$mockPrompt, $mockResearch]);
        $generator->shouldAllowMockingProtectedMethods();
        $generator->shouldReceive('resolveLlm')->andReturn($mockLlm);
        $generator->shouldReceive('getModelForProvider')->andReturn('llama3:latest');
        $generator->shouldReceive('logGeneration')->andReturnNull();
        
        $article = $generator->generate($profile, $topic, 1);

        $this->assertEquals('Test Topic', $article['title']);
        $this->assertEquals('Intro text', $article['introduction']);
        $this->assertEquals('Conclusion text', $article['conclusion']);
        $this->assertCount(2, $article['sections']);
        $this->assertEquals('Section A', $article['sections'][0]['heading']);
        $this->assertEquals('Content for A', $article['sections'][0]['content']);
        $this->assertEquals('Section B', $article['sections'][1]['heading']);
        $this->assertEquals('Content for B', $article['sections'][1]['content']);
    }
}
