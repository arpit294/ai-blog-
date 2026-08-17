<?php

namespace App\Services\Automation;

use App\Models\AutomationProfile;
use App\Models\AutomationRun;
use App\Models\BlogTopic;
use App\Services\AI\LlmProvider;
use Illuminate\Support\Facades\Log;

class TopicGenerator
{
    protected LlmProvider $llm;

    public function __construct(LlmProvider $llm)
    {
        $this->llm = $llm;
    }

    public function generate(AutomationProfile $profile, AutomationRun $run): array
    {
        $prompt = $this->buildPrompt($profile);
        $model = env('OLLAMA_MODEL', 'llama3');

        $candidates = $this->fetchCandidates($model, $prompt);

        $persisted = [];
        foreach ($candidates as $candidateData) {
            $title = $candidateData['title'] ?? null;
            if (!$title) continue;

            $normalized = static::normalizeTitle($title);
            
            $persisted[] = BlogTopic::create([
                'automation_id' => $profile->id,
                'title' => $title,
                'normalized_title' => $normalized,
                'summary' => $candidateData['summary'] ?? '',
                'category' => $candidateData['category'] ?? null,
                'intent' => $candidateData['intent'] ?? null,
                'primary_keyword' => $candidateData['primary_keyword'] ?? null,
                'status' => 'candidate',
                'source_run_id' => $run->id,
            ]);
        }

        return $persisted;
    }

    protected function fetchCandidates(string $model, string $prompt, int $attempts = 1): array
    {
        try {
            $response = $this->llm->generate($model, $prompt, ['format' => 'json']);
            
            $decoded = json_decode($response['text'], true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON returned by LLM: " . json_last_error_msg());
            }

            if (is_array($decoded) && isset($decoded['topics']) && is_array($decoded['topics'])) {
                $decoded = $decoded['topics'];
            }

            if (!is_array($decoded) || empty($decoded)) {
                throw new \Exception("LLM returned empty or non-array candidates.");
            }

            return $decoded;

        } catch (\Exception $e) {
            if ($attempts < 2) {
                Log::warning("TopicGenerator retry after failure: " . $e->getMessage());
                $stricterPrompt = $prompt . "\n\nCRITICAL INSTRUCTION: Return ONLY a valid JSON array of objects. Do not wrap it in an object like {\"topics\": [...]}. Do not include markdown formatting, backticks, or text outside the JSON array.";
                return $this->fetchCandidates($model, $stricterPrompt, $attempts + 1);
            }
            throw $e;
        }
    }

    protected function buildPrompt(AutomationProfile $profile): string
    {
        $recentAccepted = BlogTopic::where('automation_id', $profile->id)
            ->whereIn('status', ['reserved', 'used'])
            ->latest('id')
            ->take(10)
            ->pluck('title')
            ->toArray();

        $recentRejected = BlogTopic::where('automation_id', $profile->id)
            ->where('status', 'rejected')
            ->latest('id')
            ->take(10)
            ->pluck('title')
            ->toArray();

        $prompt = "You are an expert content strategist and SEO specialist.\n";
        $prompt .= "Generate 5 to 10 highly engaging blog topic candidates based on the following criteria:\n";
        $prompt .= "- Niche: {$profile->niche}\n";
        $prompt .= "- Target Audience: {$profile->target_audience}\n";
        $prompt .= "- Tone: {$profile->tone}\n";
        $prompt .= "- Language: {$profile->language}\n";

        if (!empty($recentAccepted)) {
            $prompt .= "\nAvoid generating these recently accepted topics (or similar variations):\n- " . implode("\n- ", $recentAccepted) . "\n";
        }

        if (!empty($recentRejected)) {
            $prompt .= "\nAvoid generating these recently rejected topics:\n- " . implode("\n- ", $recentRejected) . "\n";
        }

        $prompt .= "\nReturn the response as a strict JSON array of objects. Each object must have the following keys:\n";
        $prompt .= "- title (string)\n";
        $prompt .= "- summary (string, short description)\n";
        $prompt .= "- category (string)\n";
        $prompt .= "- intent (string, e.g., informational, transactional)\n";
        $prompt .= "- primary_keyword (string)\n";

        return $prompt;
    }

    public static function normalizeTitle(string $title): string
    {
        $normalized = strtolower($title);
        $normalized = preg_replace('/[^a-z0-9]/', ' ', $normalized);
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return trim($normalized);
    }
}
