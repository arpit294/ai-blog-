<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Models\Article;

use App\Models\AutomationEmbedding;
use App\Services\AI\EmbeddingProvider;
use Illuminate\Support\Facades\Log;
use App\Exceptions\AiGenerationException;

class DuplicateDetector
{
    protected EmbeddingProvider $embeddingService;

    public function __construct(EmbeddingProvider $embeddingService)
    {
        $this->embeddingService = $embeddingService;
    }
    public function checkExact(AutomationProfile $profile, string $title): bool
    {
        $normalized = TopicGenerator::normalizeTitle($title);

        $topicExists = BlogTopic::where('automation_id', $profile->id)
            ->where('normalized_title', $normalized)
            ->whereIn('status', ['reserved', 'used'])
            ->exists();

        if ($topicExists) {
            return true;
        }

        $articles = Article::where('automation_profile_id', $profile->id)->get();
        foreach ($articles as $article) {
            if (TopicGenerator::normalizeTitle($article->title) === $normalized) {
                return true;
            }
        }

        return false;
    }

    public function checkTextSimilarity(AutomationProfile $profile, string $title, string $summary): float
    {
        $normalizedTitle = TopicGenerator::normalizeTitle($title);
        $bestScore = 0.0;

        $existingTopics = BlogTopic::where('automation_id', $profile->id)
            ->whereIn('status', ['reserved', 'used'])
            ->get();

        foreach ($existingTopics as $topic) {
            $score = $this->calculateSimilarity($normalizedTitle, $topic->normalized_title);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        $existingArticles = Article::where('automation_profile_id', $profile->id)->get();
        foreach ($existingArticles as $article) {
            $articleNormalized = TopicGenerator::normalizeTitle($article->title);
            $score = $this->calculateSimilarity($normalizedTitle, $articleNormalized);
            if ($score > $bestScore) {
                $bestScore = $score;
            }
        }

        return $bestScore;
    }

    public function checkSemantic(AutomationProfile $profile, string $title, string $summary): array
    {
        $textToEmbed = trim("{$title} - {$summary}");
        $vector = $this->embeddingService->embed($textToEmbed);

        $topicIds = BlogTopic::where('automation_id', $profile->id)
            ->whereIn('status', ['reserved', 'used'])
            ->pluck('id');
            
        $articleIds = Article::where('automation_profile_id', $profile->id)
            ->pluck('id');

        $embeddings = AutomationEmbedding::where(function ($query) use ($topicIds) {
            $query->where('embeddable_type', BlogTopic::class)
                  ->whereIn('embeddable_id', $topicIds);
        })->orWhere(function ($query) use ($articleIds) {
            $query->where('embeddable_type', Article::class)
                  ->whereIn('embeddable_id', $articleIds);
        })->get();

        $bestScore = -1.0;
        $bestMatch = null;

        foreach ($embeddings as $embedding) {
            $score = $this->cosineSimilarity($vector, $embedding->vector);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $embedding;
            }
        }

        return [
            'score' => $bestScore,
            'match' => $bestMatch,
        ];
    }

    public function decide(AutomationProfile $profile, BlogTopic $candidate): array
    {
        // 1. Exact Match
        if ($this->checkExact($profile, $candidate->title)) {
            return ['decision' => 'reject', 'reason' => 'exact_duplicate'];
        }

        // 2. Text Similarity
        $textThreshold = (float) $profile->duplicate_threshold;
        $similarityScore = $this->checkTextSimilarity($profile, $candidate->title, $candidate->summary);
        
        if ($similarityScore >= $textThreshold) {
            return ['decision' => 'reject', 'reason' => 'text_duplicate'];
        }

        // 3. Semantic (Phase 5)
        try {
            $semanticResult = $this->checkSemantic($profile, $candidate->title, $candidate->summary);
            $semanticScore = $semanticResult['score'];
            $semanticMatch = $semanticResult['match'];
            $semanticThreshold = (float) ($profile->semantic_duplicate_threshold ?? 0.85);

            if ($semanticMatch && $semanticScore >= $semanticThreshold) {
                return [
                    'decision' => 'reject',
                    'reason' => 'semantic_duplicate',
                    'score' => $semanticScore,
                    'matched_type' => $semanticMatch->embeddable_type,
                    'matched_id' => $semanticMatch->embeddable_id,
                ];
            }

            if ($semanticMatch && $semanticScore >= ($semanticThreshold - 0.05)) {
                return [
                    'decision' => 'review_required',
                    'reason' => 'semantic_duplicate',
                    'score' => $semanticScore,
                    'matched_type' => $semanticMatch->embeddable_type,
                    'matched_id' => $semanticMatch->embeddable_id,
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Skipping semantic check, embedding failed: " . $e->getMessage());
            
            if ($profile->duplicate_mode === 'strict') {
                return [
                    'decision' => 'review_required',
                    'reason' => 'semantic_check_failed'
                ];
            }
        }

        // 4. Accept
        return ['decision' => 'accept', 'reason' => null];
    }

    protected function calculateSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        similar_text($str1, $str2, $percent);
        return $percent / 100.0;
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
