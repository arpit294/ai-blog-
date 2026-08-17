<?php

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\AutomationProfile;
use App\Models\QualityCheck;

class QualityChecker
{
    protected DuplicateDetector $duplicateDetector;

    public function __construct(DuplicateDetector $duplicateDetector)
    {
        $this->duplicateDetector = $duplicateDetector;
    }

    public function runChecks(Article $article, AutomationProfile $profile): QualityCheck
    {
        $contentData = json_decode($article->content, true) ?: [];
        $text = $this->extractPlainText($contentData);

        $details = [];
        $status = 'passed';

        $structureScore = $this->checkStructure($contentData, $details);
        $completenessScore = $this->checkLength($text, $profile, $details);
        
        $seoScore = $this->checkSeo($article, $details);
        $readabilityScore = $this->checkReadability($text, $details);
        $keywordScore = $this->checkKeywordUse($text, $article, $details);
        $codeScore = $this->checkCodeBlocks($contentData, $details);
        $repetitionScore = $this->checkRepetition($contentData, $details);
        $uniquenessScore = $this->checkUniqueness($article, $profile, $details);

        // Compute overall score
        $weights = config('automation.quality_weights');
        $overallScore = ($structureScore * $weights['structure']) +
                        ($completenessScore * $weights['completeness']) +
                        ($seoScore * $weights['seo']) +
                        ($readabilityScore * $weights['readability']) +
                        ($uniquenessScore * $weights['uniqueness']) +
                        (min($keywordScore, $codeScore, $repetitionScore) * $weights['technical_validity']);
        
        // Determine status based on details
        // If any regenerable failure, throw exception for RunQualityChecks to catch and handle
        
        return QualityCheck::create([
            'article_id' => $article->id,
            'overall_score' => $overallScore,
            'structure_score' => $structureScore,
            'completeness_score' => $completenessScore,
            'seo_score' => $seoScore,
            'readability_score' => $readabilityScore,
            'uniqueness_score' => $uniquenessScore,
            'technical_validity_score' => min($keywordScore, $codeScore, $repetitionScore),
            'status' => 'passed', // We will evaluate status in RunQualityChecks job
            'details' => $details,
        ]);
    }

    protected function extractPlainText(array $contentData): string
    {
        $text = $contentData['introduction'] ?? '';
        foreach ($contentData['sections'] ?? [] as $s) {
            $text .= ' ' . ($s['content'] ?? '');
        }
        $text .= ' ' . ($contentData['conclusion'] ?? '');
        return strip_tags($text);
    }

    protected function checkStructure(array $contentData, array &$details): float
    {
        $hasIntro = !empty($contentData['introduction']);
        $hasSections = !empty($contentData['sections']);
        $hasConclusion = !empty($contentData['conclusion']);

        if (!$hasIntro) $details['structure'][] = 'Missing introduction';
        if (!$hasSections) $details['structure'][] = 'Missing body sections';
        if (!$hasConclusion) $details['structure'][] = 'Missing conclusion';

        return ($hasIntro ? 0.3 : 0) + ($hasSections ? 0.4 : 0) + ($hasConclusion ? 0.3 : 0);
    }

    protected function checkLength(string $text, AutomationProfile $profile, array &$details): float
    {
        $wordCount = str_word_count($text);
        $min = $profile->min_words;
        $max = $profile->max_words;

        if ($min && $wordCount < $min) {
            $details['length'][] = "Article is too short ($wordCount words, min: $min)";
            return 0.0;
        }
        if ($max && $wordCount > $max) {
            $details['length'][] = "Article is too long ($wordCount words, max: $max)";
            return 0.0;
        }
        return 1.0;
    }

    protected function checkSeo(Article $article, array &$details): float
    {
        $seo = $article->seo;
        if (!$seo) {
            $details['seo'][] = "Missing SEO metadata";
            return 0.0;
        }

        $hasTitle = !empty($seo->seo_title);
        $hasDesc = !empty($seo->meta_description);

        if (!$hasTitle) $details['seo'][] = 'Missing seo_title';
        if (!$hasDesc) $details['seo'][] = 'Missing meta_description';

        return ($hasTitle ? 0.5 : 0) + ($hasDesc ? 0.5 : 0);
    }

    protected function checkReadability(string $text, array &$details): float
    {
        $words = str_word_count($text);
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $numSentences = count($sentences) ?: 1;
        
        $avgWordsPerSentence = $words / $numSentences;

        if ($avgWordsPerSentence > 25) {
            $details['readability'][] = 'Sentences are too long on average';
            return 0.5;
        }
        return 1.0;
    }

    protected function checkKeywordUse(string $text, Article $article, array &$details): float
    {
        $keyword = $article->seo?->focus_keyword;
        if (!$keyword) return 1.0;

        $textLower = strtolower($text);
        $keywordLower = strtolower($keyword);
        
        $count = substr_count($textLower, $keywordLower);
        $words = str_word_count($text) ?: 1;

        $density = ($count * str_word_count($keyword)) / $words;

        if ($density > 0.05) {
            $details['keyword_use'][] = 'Keyword stuffing detected (density > 5%)';
            return 0.0;
        }
        return 1.0;
    }

    protected function checkCodeBlocks(array $contentData, array &$details): float
    {
        $raw = json_encode($contentData);
        $backticksCount = substr_count($raw, '```');
        
        if ($backticksCount % 2 !== 0) {
            $details['code_blocks'][] = 'Unmatched markdown code blocks detected';
            return 0.0;
        }
        return 1.0;
    }

    protected function checkRepetition(array $contentData, array &$details): float
    {
        $sections = $contentData['sections'] ?? [];
        if (count($sections) < 2) return 1.0;

        for ($i = 0; $i < count($sections); $i++) {
            for ($j = $i + 1; $j < count($sections); $j++) {
                $content1 = strip_tags($sections[$i]['content'] ?? '');
                $content2 = strip_tags($sections[$j]['content'] ?? '');
                
                similar_text($content1, $content2, $percent);
                if ($percent > 70) {
                    $details['repetition'][] = "High similarity between sections";
                    return 0.0;
                }
            }
        }
        return 1.0;
    }

    protected function checkUniqueness(Article $article, AutomationProfile $profile, array &$details): float
    {
        $similarity = clone $this->duplicateDetector;
        $title = $article->title;
        $summary = $article->excerpt ?? '';
        
        $result = $similarity->checkSemantic($profile, $title, $summary);
        
        $threshold = (float) ($profile->semantic_duplicate_threshold ?? 0.85);
        if ($result['score'] >= $threshold) {
            $details['uniqueness'][] = "Semantic duplication detected with past content";
            return 0.0;
        }
        
        return 1.0;
    }
}
