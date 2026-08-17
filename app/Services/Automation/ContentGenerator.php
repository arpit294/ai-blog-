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
    protected LlmProvider $llm;
    protected PromptService $promptService;
    protected string $modelName;

    public function __construct(LlmProvider $llm, PromptService $promptService)
    {
        $this->llm = $llm;
        $this->promptService = $promptService;
        $this->modelName = env('OLLAMA_MODEL', 'qwen2.5:latest');
    }

    public function generate(AutomationProfile $profile, BlogTopic $topic, int $runId): array
    {
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
                'outline' => json_encode($headings)
            ]);
            $sections[] = [
                'heading' => $heading,
                'content' => $section['content'] ?? ''
            ];
        }

        // 4. Assembly
        $assembled = $this->runStage('assembly', $profile, $topic, $runId, ['sections' => $sections]);

        // 5. Consistency
        $finalArticle = $this->runStage('consistency', $profile, $topic, $runId, ['article' => $assembled]);

        // Validate final structure
        if (!isset($finalArticle['title']) || !isset($finalArticle['sections'])) {
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
                $response = $this->llm->generate($this->modelName, $prompt, ['format' => 'json']);
                $text = $response['text'];
                $data = json_decode($text, true);
                
                if (isset($data['content'])) {
                    $article['sections'][$targetIndex]['content'] = $data['content'];
                }
                
                $this->logGeneration($runId, 'content_generation_word_count_adjustment', $this->promptService->getVersion(), 'success', (int)((microtime(true)-$start)*1000), $text);
            } catch (\Exception $e) {
                $this->logGeneration($runId, 'content_generation_word_count_adjustment', $this->promptService->getVersion(), 'failed', (int)((microtime(true)-$start)*1000), '');
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

        $response = $this->llm->generate($this->modelName, $prompt, 'json');
        $data = json_decode($response['text'], true);
        return $data['content'] ?? '';
    }

    protected function runStage(string $stage, AutomationProfile $profile, BlogTopic $topic, int $runId, array $extraContext = []): array
    {
        $prompt = $this->promptService->buildPrompt($stage, $profile, $topic, $extraContext);
        
        $start = microtime(true);
        $status = 'failed';
        $text = '';
        
        try {
            $response = $this->llm->generate($this->modelName, $prompt, ['format' => 'json']);
            $text = $response['text'];
            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning("ContentGenerator: Invalid JSON at stage {$stage}, retrying.");
                $prompt .= " IMPORTANT: You MUST output strictly valid JSON. Escape quotes properly.";
                $response = $this->llm->generate($this->modelName, $prompt, ['format' => 'json']);
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
            $this->logGeneration($runId, 'content_generation_' . $stage, $this->promptService->getVersion(), $status, $duration, $text);
        }
    }

    protected function logGeneration(int $runId, string $taskType, string $version, string $status, int $duration, string $output)
    {
        $generation = AiGeneration::create([
            'run_id' => $runId,
            'task_type' => $taskType,
            'model' => $this->modelName,
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
