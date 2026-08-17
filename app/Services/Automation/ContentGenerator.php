<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Log;

class ContentGenerator
{
    protected PromptService $promptService;
    protected ResearchService $researchService;

    public function __construct(PromptService $promptService, ResearchService $researchService)
    {
        $this->promptService = $promptService;
        $this->researchService = $researchService;
    }

    public function generate(AutomationProfile $profile, BlogTopic $topic, int $runId): array
    {
        // 0. Research (RAG Fact Extraction)
        $facts = [];
        if (!empty($topic->primary_keyword) || !empty($topic->target_keyword)) {
            $keywordToResearch = $topic->target_keyword ?? $topic->primary_keyword;
            try {
                // Article hasn't been fully persisted yet, wait, we have a $topic, but we need an Article ID for citations.
                // We'll create the Article placeholder first if needed, or pass 0. Let's just create a draft Article now.
                $article = Article::firstOrCreate(['topic_id' => $topic->id], [
                    'automation_id' => $profile->id,
                    'title' => $topic->title,
                    'status' => 'draft',
                    'slug' => \Illuminate\Support\Str::slug($topic->title),
                ]);
                $facts = $this->researchService->research($article->id, $keywordToResearch);
            } catch (\Exception $e) {
                Log::warning("Research stage failed, falling back to pure LLM generation. " . $e->getMessage());
            }
        }

        // 1. Brief
        $brief = $this->runStage('brief', $profile, $topic, $runId, []);
        
        // 2. Outline
        $outline = $this->runStage('outline', $profile, $topic, $runId, ['brief' => $brief['brief'] ?? '']);
        
        // 3. Sections
        $sections = [];
        $headings = $outline['headings'] ?? [];
        foreach ($headings as $heading) {
            $section = $this->runStage('section', $profile, $topic, $runId, [
                'section' => $heading,
                'outline' => json_encode($headings),
                'facts' => $facts
            ]);
            $sections[] = [
                'heading' => $heading,
                'content' => $section['content'] ?? ''
            ];
        }

        // 4. Intro & Conclusion
        $intro = $this->runStage('introduction', $profile, $topic, $runId, [
            'brief' => $brief['brief'] ?? '',
            'outline' => $headings,
            'facts' => $facts
        ]);
        
        $conclusion = $this->runStage('conclusion', $profile, $topic, $runId, [
            'outline' => $headings
        ]);

        // 5. Manual Assembly
        $finalArticle = [
            'title' => $topic->title,
            'slug' => \Illuminate\Support\Str::slug($topic->title),
            'excerpt' => $topic->summary,
            'introduction' => $intro['introduction'] ?? '',
            'sections' => $sections,
            'conclusion' => $conclusion['conclusion'] ?? '',
            'faq' => [] // FAQ generation can be added later if needed
        ];

        // Validate final structure
        if (empty($finalArticle['title']) || empty($finalArticle['sections'])) {
            throw new AiGenerationException("Final assembled article missing required structured fields.");
        }

        // Word count enforcement
        $wordCount = $this->calculateWordCount($finalArticle);
        if ($profile->min_words && $wordCount < $profile->min_words) {
            $finalArticle = $this->adjustWordCount($finalArticle, $profile, $topic, $runId, 'extend');
        } elseif ($profile->max_words && $wordCount > $profile->max_words) {
            $finalArticle = $this->adjustWordCount($finalArticle, $profile, $topic, $runId, 'compress');
        }

        return $finalArticle;
    }

    protected function calculateWordCount(array $article): int
    {
        $text = $article['introduction'] ?? '';
        foreach ($article['sections'] as $s) {
            $text .= ' ' . ($s['content'] ?? '');
        }
        $text .= ' ' . ($article['conclusion'] ?? '');
        return str_word_count(strip_tags($text));
    }

    protected function adjustWordCount(array $article, AutomationProfile $profile, BlogTopic $topic, int $runId, string $direction): array
    {
        $targetIndex = -1;
        $targetLength = $direction === 'extend' ? PHP_INT_MAX : -1;
        
        foreach ($article['sections'] as $i => $section) {
            $len = str_word_count(strip_tags($section['content'] ?? ''));
            if ($direction === 'extend' && $len < $targetLength) {
                $targetLength = $len;
                $targetIndex = $i;
            } elseif ($direction === 'compress' && $len > $targetLength) {
                $targetLength = $len;
                $targetIndex = $i;
            }
        }

        if ($targetIndex !== -1) {
            $section = $article['sections'][$targetIndex];
            $reason = "The article is " . ($direction === 'extend' ? "too short" : "too long") . ". Please " . ($direction === 'extend' ? "expand" : "condense") . " this section.";
            
            $prompt = $this->promptService->buildPrompt('regenerate_section', $profile, $topic, [
                'section' => $section['heading'],
                'reason' => $reason
            ]);

            $start = microtime(true);
            try {
                $llm = $this->resolveLlm('section_content_generation', $profile);
                $response = $llm->generate($this->getModelForProvider('section_content_generation'), $prompt, ['format' => 'json']);
                $text = $response['text'];
                $data = json_decode($text, true);
                
                if (isset($data['content'])) {
                    $article['sections'][$targetIndex]['content'] = $data['content'];
                }
                
                $this->logGeneration($runId, 'content_generation_word_count_adjustment', $this->promptService->getVersion(), 'success', (int)((microtime(true)-$start)*1000), $text, $this->getModelForProvider('section_content_generation'));
            } catch (\Exception $e) {
                $this->logGeneration($runId, 'content_generation_word_count_adjustment', $this->promptService->getVersion(), 'failed', (int)((microtime(true)-$start)*1000), '', $this->getModelForProvider('section_content_generation'));
                throw $e;
            }
        }
        
        return $article;
    }

    public function regenerateSection(Article $article, string $sectionHeading, string $reason): string
    {
        $topic = $article->topic; // Needs topic relationship
        $profile = $article->profile;

        if (!$topic || !$profile) {
            throw new \Exception("Cannot regenerate section without topic and profile.");
        }

        $prompt = $this->promptService->buildPrompt('regenerate_section', $profile, $topic, [
            'section' => $sectionHeading,
            'reason' => $reason
        ]);

        $llm = $this->resolveLlm('section_content_generation', $profile);
        $response = $llm->generate($this->getModelForProvider('section_content_generation'), $prompt, 'json');
        $data = json_decode($response['text'], true);
        return $data['content'] ?? '';
    }

    protected function runStage(string $stage, AutomationProfile $profile, BlogTopic $topic, int $runId, array $extraContext = []): array
    {
        $prompt = $this->promptService->buildPrompt($stage, $profile, $topic, $extraContext);
        
        $start = microtime(true);
        $status = 'failed';
        $text = '';
        
        $taskTypeMap = [
            'brief' => 'outline_generation',
            'outline' => 'outline_generation',
            'section' => 'section_content_generation',
            'introduction' => 'section_content_generation',
            'conclusion' => 'section_content_generation',
        ];
        
        $taskType = $taskTypeMap[$stage] ?? 'outline_generation';
        $llm = $this->resolveLlm($taskType, $profile);
        $model = $this->getModelForProvider($taskType);
        
        try {
            $response = $llm->generate($model, $prompt, ['format' => 'json']);
            $text = $response['text'];
            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("ContentGenerator: Invalid JSON at stage {$stage}, retrying.");
                $prompt .= " IMPORTANT: You MUST output strictly valid JSON. Escape quotes properly.";
                $response = $llm->generate($model, $prompt, ['format' => 'json']);
                $text = $response['text'];
                $data = json_decode($text, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new AiGenerationException("Invalid JSON output at stage {$stage}");
                }
            }

            $status = 'success';
            return $data;

        } catch (\Exception $e) {
            throw $e;
        } finally {
            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->logGeneration($runId, 'content_generation_' . $stage, $this->promptService->getVersion(), $status, $duration, $text, $model ?? 'unknown');
        }
    }

    protected function resolveLlm(string $taskType, AutomationProfile $profile): \App\Services\AI\LlmProvider
    {
        $providerName = config("automation.ai_providers.{$taskType}", 'ollama');
        if ($providerName === 'groq') {
            return app(\App\Services\AI\GroqService::class);
        }
        return app(\App\Services\AI\OllamaService::class);
    }

    protected function getModelForProvider(string $taskType): string
    {
        $providerName = config("automation.ai_providers.{$taskType}", 'ollama');
        if ($providerName === 'groq') {
            return 'llama3-70b-8192'; // Using Llama3-70b on Groq for heavy lifting
        }
        return config('services.llm.model', 'llama3:latest'); // Ollama default
    }

    protected function logGeneration(int $runId, string $taskType, string $version, string $status, int $duration, string $output, string $modelName)
    {
        $generation = AiGeneration::create([
            'run_id' => $runId,
            'task_type' => $taskType,
            'model' => $modelName,
            'prompt_version' => $version,
            'status' => $status,
            'duration_ms' => $duration,
        ]);

        $generation->logs()->create([
            'event' => 'completed',
            'payload_ref' => substr($output, 0, 1000), 
        ]);
    }
}
