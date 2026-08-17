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
            $facts = $extraContext['facts'] ?? [];
            
            $prompt = "Task: Write comprehensive, high-quality content for the specific section: '{$section}'. Consider the full outline context: {$outline}.\n";
            
            if (!empty($facts)) {
                $factsList = implode("\n- ", $facts);
                $prompt .= "CRITICAL INSTRUCTION: Use the following verified research facts to ground your content. Do not copy these facts verbatim; integrate them naturally into your writing in your own words:\n- {$factsList}\n\n";
            }
            
            $prompt .= "Output JSON format: { \"content\": \"string with HTML/markdown\" }";
            return $prompt;
        }
        if ($stage === 'introduction') {
            $brief = $extraContext['brief'] ?? '';
            $outline = json_encode($extraContext['outline'] ?? []);
            $facts = $extraContext['facts'] ?? [];

            $prompt = "Task: Write a compelling, engaging introduction for the article. Set the hook and outline what the reader will learn. Consider the brief: {$brief} and the full outline: {$outline}.\n";
            
            if (!empty($facts)) {
                $factsList = implode("\n- ", $facts);
                $prompt .= "Use these verified facts if applicable: \n- {$factsList}\n\n";
            }
            
            $prompt .= "Output JSON format: { \"introduction\": \"string (HTML)\" }";
            return $prompt;
        }
        if ($stage === 'conclusion') {
            $outline = json_encode($extraContext['outline'] ?? []);
            return "Task: Write a strong, summarizing conclusion for the article based on the outline: {$outline}. Provide final takeaways. Output JSON format: { \"conclusion\": \"string (HTML)\" }";
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
            $style = $extraContext['profile']['image_style'] ?? 'Photorealistic Editorial';
            $topic = $extraContext['topic']['title'] ?? '';
            
            $prompt = "Task: Create a visual prompt for an AI image generator (like Stable Diffusion or Midjourney) to create a featured image for this blog article.\n\n";
            $prompt .= "Article Topic: {$topic}\n";
            $prompt .= "Required Style/Aesthetic: {$style}\n\n";
            $prompt .= "CRITICAL CONSTRAINTS:\n";
            $prompt .= "1. Focus heavily on visual elements, lighting, lens details, and aesthetic quality.\n";
            $prompt .= "2. ABSOLUTELY NO TEXT, letters, words, logos, or watermarks should appear in the image.\n\n";
            $prompt .= "Output JSON format: { \"prompt\": \"string\" }";
            return $prompt;
        }
        if ($stage === 'image_alt_text') {
            $imagePrompt = $extraContext['image_prompt'] ?? '';
            return "Task: Generate an SEO-friendly, descriptive alt-text for the featured image of this article. The image was generated with this visual prompt: {$imagePrompt}. Keep it under 125 characters. Output JSON format: { \"alt_text\": \"string\" }";
        }
        
        return "";
    }
}
