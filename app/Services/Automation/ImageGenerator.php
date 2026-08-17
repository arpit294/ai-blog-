<?php

namespace App\Services\Automation;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Services\AI\LlmProvider;
use App\Services\AI\PromptService;
use App\Services\AI\ImageProvider;
use App\Services\AI\ImageRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageGenerator
{
    protected LlmProvider $llm;
    protected PromptService $promptService;
    protected ImageProvider $imageProvider;

    public function __construct(LlmProvider $llm, PromptService $promptService, ImageProvider $imageProvider)
    {
        $this->llm = $llm;
        $this->promptService = $promptService;
        $this->imageProvider = $imageProvider;
    }

    public function generate(Article $article): ArticleImage
    {
        // 1. Build prompt
        $promptText = $this->buildImagePrompt($article);

        // 2. Call Image Provider
        $request = new ImageRequest($promptText, 1024, 1024);
        $result = $this->imageProvider->generate($request);

        // 3. Validate and Resize
        $manager = new ImageManager(new Driver());
        $image = $manager->decode($result->raw_bytes);
        $image->cover(1024, 1024);
        $processedBytes = (string) $image->encodeUsingFileExtension('jpg', 85);

        // 4. Store
        $disk = config('automation.image_disk', 'public');
        $filename = 'articles/' . $article->id . '/' . Str::uuid() . '.jpg';
        Storage::disk($disk)->put($filename, $processedBytes);

        // 5. Generate Alt text
        $altText = $this->generateAltText($article, $promptText);

        // 6. Save DB
        $articleImage = ArticleImage::updateOrCreate(
            ['article_id' => $article->id], // Enforce 1 featured image per article
            [
                'path' => $filename,
                'prompt' => $promptText,
                'provider' => class_basename($this->imageProvider),
                'provider_generation_id' => $result->provider_generation_id,
                'width' => 1024,
                'height' => 1024,
                'alt_text' => $altText,
                'status' => 'generated',
            ]
        );

        return $articleImage;
    }

    public function buildImagePrompt(Article $article): string
    {
        $prompt = $this->promptService->buildPrompt('image_prompt', $article->profile, $article->topic);
        $response = $this->llm->generate('qwen2.5', $prompt, ['format' => 'json']);
        $data = json_decode($response['text'], true);
        return $data['prompt'] ?? $article->title;
    }

    public function generateAltText(Article $article, string $imagePrompt): string
    {
        $prompt = $this->promptService->buildPrompt('image_alt_text', $article->profile, $article->topic, ['image_prompt' => $imagePrompt]);
        $response = $this->llm->generate('qwen2.5', $prompt, ['format' => 'json']);
        $data = json_decode($response['text'], true);
        return $data['alt_text'] ?? $article->title;
    }
}
