<?php

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\AutomationEmbedding;
use App\Models\AutomationProfile;
use App\Services\AI\EmbeddingProvider;

class InternalLinkingService
{
    protected EmbeddingProvider $embeddingService;

    public function __construct(EmbeddingProvider $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }

    /**
     * Finds related published articles for internal linking.
     */
    public function getSuggestions(AutomationProfile $profile, string $currentTopicTitle, string $currentTopicSummary, int $limit = 3): array
    {
        $textToEmbed = trim("{$currentTopicTitle} - {$currentTopicSummary}");
        
        try {
            $vector = $this->embeddingService->embed($textToEmbed);
        } catch (\Exception $e) {
            return []; // Fail gracefully if embeddings are down
        }

        // Only suggest articles that are published (or content_generated)
        $articleIds = Article::where('automation_profile_id', $profile->id)
            ->whereIn('status', ['published', 'content_generated', 'completed'])
            ->pluck('id');

        if ($articleIds->isEmpty()) {
            return [];
        }

        $embeddings = AutomationEmbedding::where('embeddable_type', Article::class)
            ->whereIn('embeddable_id', $articleIds)
            ->get();

        $scored = [];

        foreach ($embeddings as $embedding) {
            $score = $this->cosineSimilarity($vector, $embedding->vector);
            if ($score > 0.75) { // Minimum semantic threshold for a "related" link
                $scored[] = [
                    'score' => $score,
                    'article_id' => $embedding->embeddable_id,
                ];
            }
        }

        usort($scored, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $topScored = array_slice($scored, 0, $limit);
        
        $suggestions = [];
        foreach ($topScored as $item) {
            $article = Article::find($item['article_id']);
            if ($article && !empty($article->slug)) {
                $suggestions[] = [
                    'title' => $article->title,
                    'url' => '/' . $article->slug, // Assuming a standard slug route
                ];
            }
        }

        return $suggestions;
    }

    protected function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $mag1 = 0.0;
        $mag2 = 0.0;

        foreach ($vec1 as $i => $v1) {
            $v2 = $vec2[$i] ?? 0.0;
            $dotProduct += $v1 * $v2;
            $mag1 += $v1 * $v1;
            $mag2 += $v2 * $v2;
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 == 0 || $mag2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($mag1 * $mag2);
    }
}
