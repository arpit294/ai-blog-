<?php

namespace App\Services\AI\Prompts\V1;

class ContentPrompts
{
    public const VERSION = 'v1.0';

    public static function getSystemPrompt(): string
    {
        return "You are an expert, professional SEO content writer. Your task is to output strictly structured JSON. Do NOT output markdown formatting outside the JSON values. Ensure the output is valid JSON.";
    }

    public static function getProfilePrompt(array $profile): string
    {
        $prompt = "Niche: {$profile['niche']}\n"
             . "Audience: {$profile['target_audience']}\n"
             . "Language: {$profile['language']}\n"
             . "Tone: {$profile['tone']}\n";
             
        if (!empty($profile['min_words'])) {
            $prompt .= "Target Length: YOU MUST WRITE AT LEAST {$profile['min_words']} WORDS IN TOTAL.\n"
                     . "Quality Standard: The content must be highly relevant, expertly researched, and strictly related to the topic. DO NOT add fluff, repetitive sentences, or off-topic filler just to reach the word count. Provide deep analysis, the absolute best quality, and extremely detailed and comprehensive paragraphs.\n";
        }
        
        return $prompt;
    }

    public static function getTopicPrompt(array $topic): string
    {
        return "Topic Title: {$topic['title']}\n"
             . "Summary: {$topic['summary']}\n"
             . "Intent: {$topic['intent']}\n"
             . "Primary Keyword: {$topic['primary_keyword']}\n";
    }

    public static function getStructurePrompt(string $stage, array $extraContext = []): string
    {
        if ($stage === 'brief') {
            return "Task: Generate a creative brief (3-4 sentences outlining the angle and value proposition). Output JSON format: { \"brief\": \"string\" }";
        }
        if ($stage === 'outline') {
            $brief = $extraContext['brief'] ?? '';
            return "Task: Generate a detailed outline based on the brief: {$brief}. Output JSON format: { \"headings\": [\"string\", \"string\"] }";
        }
        if ($stage === 'section') {
            $section = $extraContext['section'] ?? '';
            $outline = $extraContext['outline'] ?? '';
            return "Task: Write comprehensive, high-quality content for the specific section: '{$section}'. Consider the full outline context: {$outline}. Output JSON format: { \"content\": \"string with HTML/markdown\" }";
        }
        if ($stage === 'assembly') {
            $sections = json_encode($extraContext['sections'] ?? []);
            return "Task: Assemble the final structured article using the provided sections: {$sections}. You must output exactly the JSON structure requested. Output JSON format: { \"title\": \"string\", \"slug\": \"string (kebab-case)\", \"excerpt\": \"string (meta description)\", \"introduction\": \"string (HTML)\", \"sections\": [ { \"heading\": \"string\", \"content\": \"string (HTML)\" } ], \"conclusion\": \"string (HTML)\", \"faq\": [ { \"question\": \"string\", \"answer\": \"string (HTML)\" } ] }";
        }
        if ($stage === 'consistency') {
            $article = json_encode($extraContext['article'] ?? []);
            return "Task: Review the assembled article for consistency, flow, and completeness: {$article}. Fix any disjointed transitions. Output JSON format exactly as before: { \"title\": \"...\", \"slug\": \"...\", \"excerpt\": \"...\", \"introduction\": \"...\", \"sections\": [...], \"conclusion\": \"...\", \"faq\": [...] }";
        }
        if ($stage === 'regenerate_section') {
            $section = $extraContext['section'] ?? '';
            $reason = $extraContext['reason'] ?? '';
            return "Task: Regenerate the specific section: '{$section}'. The reason for regeneration is: {$reason}. Adjust the content accordingly. Output JSON format: { \"content\": \"string with HTML/markdown\" }";
        }
        if ($stage === 'seo_generation') {
            $article = json_encode($extraContext['article'] ?? []);
            $internalLinks = json_encode($extraContext['internal_links'] ?? []);
            return "Task: Generate SEO metadata for the provided article. Prioritize search intent, clear structure, useful headings, concise metadata, natural language, and internal-link opportunities over keyword stuffing.\n"
                 . "Article: {$article}\n"
                 . "Available Internal Links: {$internalLinks}\n"
                 . "Output JSON format: { \"seo_title\": \"string\", \"meta_description\": \"string\", \"focus_keyword\": \"string\", \"secondary_keywords\": [\"string\"], \"canonical_url\": \"string\", \"og_title\": \"string\", \"og_description\": \"string\", \"faq_schema\": {}, \"article_schema\": {}, \"internal_link_suggestions\": [ { \"anchor_text\": \"string\", \"url\": \"string\" } ] }";
        }
        if ($stage === 'image_prompt') {
            return "Task: Create a visual prompt for an AI image generator (like Stable Diffusion or Midjourney) to create a featured image for this blog article. Keep it concise, descriptive, and focused on visual elements, lighting, and style. Output JSON format: { \"prompt\": \"string\" }";
        }
        if ($stage === 'image_alt_text') {
            $imagePrompt = $extraContext['image_prompt'] ?? '';
            return "Task: Generate an SEO-friendly, descriptive alt-text for the featured image of this article. The image was generated with this visual prompt: {$imagePrompt}. Keep it under 125 characters. Output JSON format: { \"alt_text\": \"string\" }";
        }
        
        return "";
    }
}
