<?php

namespace App\Services\AI;

use App\Models\AutomationProfile;
use App\Models\BlogTopic;
use App\Services\AI\Prompts\V1\ContentPrompts;

class PromptService
{
    public function getVersion(): string
    {
        return ContentPrompts::VERSION;
    }

    public function buildPrompt(string $stage, AutomationProfile $profile, BlogTopic $topic, array $extraContext = []): string
    {
        $system = ContentPrompts::getSystemPrompt();
        
        $profileCtx = ContentPrompts::getProfilePrompt([
            'niche' => $profile->niche,
            'target_audience' => $profile->target_audience,
            'language' => $profile->language,
            'tone' => $profile->tone,
            'min_words' => $profile->min_words,
            'max_words' => $profile->max_words,
        ]);
        
        $topicCtx = ContentPrompts::getTopicPrompt([
            'title' => $topic->title,
            'summary' => $topic->summary,
            'intent' => $topic->intent,
            'primary_keyword' => $topic->primary_keyword,
        ]);
        
        $structure = ContentPrompts::getStructurePrompt($stage, $extraContext);

        return "{$system}\n\n[PROFILE CONTEXT]\n{$profileCtx}\n\n[TOPIC CONTEXT]\n{$topicCtx}\n\n[STRUCTURE & TASK]\n{$structure}";
    }
}
