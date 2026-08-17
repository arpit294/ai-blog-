<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Article;
use App\Services\AI\ImageProvider;
use App\Services\AI\ImageRequest;
use App\Services\AI\PromptService;
use App\Services\AI\LlmProvider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GenerateImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $article;
    public $tries = 3;
    public $timeout = 180;

    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    public function handle(ImageProvider $imageProvider, LlmProvider $llm, PromptService $promptService)
    {
        try {
            // Check if article already has an image
            if ($this->article->featured_image_path) {
                return;
            }

            // Generate the image prompt using LLM
            $promptContext = $promptService->buildPrompt('image_prompt', $this->article->profile, $this->article->topic, [
                'profile' => $this->article->profile->toArray(),
            ]);
            $response = $llm->generate(config('services.llm.model'), $promptContext, ['format' => 'json']);
            
            $data = json_decode($response['text'], true);
            $imagePromptStr = $data['prompt'] ?? 'A professional blog featured image related to ' . $this->article->title;

            // Request image from fal.ai
            $aspectRatio = $this->article->profile->image_aspect_ratio ?? '16:9';
            $lora = $this->article->profile->image_lora ?? null;
            $imageRequest = new ImageRequest($imagePromptStr, 1024, 768, $aspectRatio, $lora);
            $imageResult = $imageProvider->generate($imageRequest);

            // Store image
            $filename = 'articles/' . $this->article->id . '_' . $imageResult->id . '.jpg';
            Storage::disk('public')->put($filename, $imageResult->rawBytes);

            // Update article
            $this->article->update([
                'featured_image_path' => 'storage/' . $filename
            ]);

        } catch (\Exception $e) {
            // Log the error but DO NOT throw. Image failure must never block the article.
            Log::error("GenerateImage job failed for article {$this->article->id}: " . $e->getMessage());
            // Proceed without image
        }
    }
}
