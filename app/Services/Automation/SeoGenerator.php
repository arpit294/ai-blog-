<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\Article;
use App\Models\ArticleSeo;
use App\Models\AiGeneration;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SeoGenerator
{
    protected LlmProvider $llm;
    protected PromptService $promptService;
    protected string $modelName = 'qwen2.5'; // Using a default from previous phases

    public function __construct(LlmProvider $llm, PromptService $promptService)
    {
        $this->llm = $llm;
        $this->promptService = $promptService;
    }

    public function generate(AutomationProfile $profile, Article $article, int $runId): ArticleSeo
    {
        $topic = $article->topic;

        // Fetch potential internal links
        $publishedArticles = Article::where('automation_profile_id', $profile->id)
            ->where('status', 'published')
            ->where('id', '!=', $article->id)
            ->select('id', 'title', 'slug')
            ->limit(10)
            ->get()
            ->map(fn($a) => ['title' => $a->title, 'url' => url('/' . $a->slug)])
            ->toArray();

        $prompt = $this->promptService->buildPrompt('seo_generation', $profile, $topic, [
            'article' => [
                'title' => $article->title,
                'content' => $article->content
            ],
            'internal_links' => $publishedArticles
        ]);

        $start = microtime(true);
        try {
            $response = $this->llm->generate($this->modelName, $prompt, ['format' => 'json']);
            $text = $response['text'];
            $data = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['seo_title'])) {
                Log::warning("SeoGenerator: Invalid JSON, retrying.");
                $prompt .= " IMPORTANT: You MUST output strictly valid JSON. Escape quotes properly. Ensure all fields are present.";
                $response = $this->llm->generate($this->modelName, $prompt, ['format' => 'json']);
                $text = $response['text'];
                $data = json_decode($text, true);
                if (json_last_error() !== JSON_ERROR_NONE || !isset($data['seo_title'])) {
                    throw new AiGenerationException("Invalid JSON output for SEO generation");
                }
            }

            $this->logGeneration($runId, 'seo_generation', $this->promptService->getVersion(), 'success', (int)((microtime(true)-$start)*1000), $text);

            return ArticleSeo::create([
                'article_id' => $article->id,
                'seo_title' => $data['seo_title'],
                'meta_description' => $data['meta_description'] ?? '',
                'focus_keyword' => $data['focus_keyword'] ?? null,
                'secondary_keywords' => $data['secondary_keywords'] ?? null,
                'slug' => $article->slug,
                'canonical_url' => $data['canonical_url'] ?? null,
                'og_title' => $data['og_title'] ?? null,
                'og_description' => $data['og_description'] ?? null,
                'faq_schema' => $data['faq_schema'] ?? null,
                'article_schema' => $data['article_schema'] ?? null,
                'internal_link_suggestions' => $data['internal_link_suggestions'] ?? null,
                'prompt_version' => $this->promptService->getVersion(),
            ]);

        } catch (\Exception $e) {
            $this->logGeneration($runId, 'seo_generation', $this->promptService->getVersion(), 'failed', (int)((microtime(true)-$start)*1000), '');
            throw $e;
        }
    }

    protected function logGeneration(int $runId, string $taskType, string $version, string $status, int $duration, string $payload)
    {
        $gen = AiGeneration::create([
            'run_id' => $runId,
            'task_type' => $taskType,
            'model' => $this->modelName,
            'prompt_version' => $version,
            'status' => $status,
            'duration_ms' => $duration
        ]);

        $gen->logs()->create([
            'event' => 'completed',
            'payload_ref' => json_encode(['response' => $payload])
        ]);
    }
}
