<?php

namespace App\Services\Automation;

use App\Services\AI\WebSearchProvider;
use App\Services\AI\LlmProvider;
use App\Models\ArticleResearchSource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ResearchService
{
    protected WebSearchProvider $searchProvider;
    protected LlmProvider $llm;

    public function __construct(WebSearchProvider $searchProvider, LlmProvider $llm)
    {
        $this->searchProvider = $searchProvider;
        $this->llm = $llm;
    }

    /**
     * Search the web, extract facts, and return a condensed list.
     * Also saves the sources to the database for citations.
     */
    public function research(int $articleId, string $primaryKeyword): array
    {
        // Check cache (per keyword) so we don't re-search the same thing often
        $cacheKey = "research_facts_" . md5($primaryKeyword);
        $cachedFacts = Cache::get($cacheKey);

        if ($cachedFacts) {
            Log::info("Loaded research facts from cache for: {$primaryKeyword}");
            return $cachedFacts;
        }

        $results = $this->searchProvider->search($primaryKeyword, 3);
        if (empty($results)) {
            return [];
        }

        $facts = [];
        $sources = [];

        foreach ($results as $result) {
            $content = $result['raw_content'] ?? $result['snippet'];
            
            if (empty($content)) {
                continue;
            }

            // Ask the LLM to extract facts
            $prompt = "You are an expert researcher. Extract 3 to 5 discrete, factual bullet points from the following text.\n"
                    . "Do NOT copy large sentences verbatim. Rephrase into concise facts.\n"
                    . "Text:\n{$content}\n\n"
                    . "Output ONLY a JSON array of strings (e.g. [\"Fact 1\", \"Fact 2\"]).";

            try {
                // Use a fast model like llama3 or groq for this (configured via LlmProvider later)
                $response = $this->llm->generate('llama3:latest', $prompt, ['format' => 'json']);
                $decoded = json_decode($response['text'], true);

                if (is_array($decoded)) {
                    // Sometimes LLMs wrap it in an object like {"facts": [...]}
                    $extracted = isset($decoded['facts']) ? $decoded['facts'] : $decoded;
                    
                    if (is_array($extracted)) {
                        $facts = array_merge($facts, $extracted);
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Fact extraction failed for URL {$result['url']}: " . $e->getMessage());
            }

            $sources[] = [
                'article_id' => $articleId,
                'url' => $result['url'],
                'title' => $result['title'],
                'snippet' => mb_substr($result['snippet'], 0, 500)
            ];
        }

        // Clean up facts
        $facts = array_filter(array_unique($facts));
        $facts = array_slice($facts, 0, 10); // Keep top 10 facts max

        // Save sources to DB
        foreach ($sources as $sourceData) {
            ArticleResearchSource::create($sourceData);
        }

        if (!empty($facts)) {
            Cache::put($cacheKey, $facts, now()->addDays(30));
        }

        return $facts;
    }
}
