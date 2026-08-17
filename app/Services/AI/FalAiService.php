<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Exceptions\AiGenerationException;

class FalAiService implements ImageProvider
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.fal.api_key', env('FAL_KEY', ''));
    }

    public function generate(ImageRequest $request): ImageResult
    {
        if (empty($this->apiKey)) {
            throw new AiGenerationException("fal.ai API key is missing. Please set FAL_KEY in .env.");
        }

        // Map common ratios to fal.ai image sizes
        $ratioMap = [
            '16:9' => 'landscape_16_9',
            '4:3' => 'landscape_4_3',
            '1:1' => 'square_hd',
            '3:4' => 'portrait_4_3',
            '9:16' => 'portrait_16_9',
        ];
        
        $imageSize = $ratioMap[$request->aspectRatio] ?? 'landscape_16_9';
        
        $payload = [
            'prompt' => $request->prompt,
            'image_size' => $imageSize,
            'num_images' => 1,
            'enable_safety_checker' => true
        ];

        // Currently, fal.ai flux/schnell doesn't natively accept loras in the standard payload as simply as SD,
        // but if using flux/dev, it's accepted via 'loras' array. We will inject it if present.
        if (!empty($request->lora)) {
            $url = 'https://fal.run/fal-ai/flux/dev'; // Switch to dev model if LoRA is used
            $payload['loras'] = [
                ['path' => $request->lora, 'scale' => 1.0]
            ];
        } else {
            $url = 'https://fal.run/fal-ai/flux/schnell';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post($url, $payload);

        if ($response->failed()) {
            throw new AiGenerationException("fal.ai image generation failed: " . $response->body());
        }

        $data = $response->json();
        
        if (!isset($data['images'][0]['url'])) {
            throw new AiGenerationException("Invalid response from fal.ai: " . json_encode($data));
        }

        $imageUrl = $data['images'][0]['url'];
        
        $imageResponse = Http::timeout(60)->get($imageUrl);
        if ($imageResponse->failed()) {
            throw new AiGenerationException("Failed to download generated image from fal.ai.");
        }

        $rawBytes = $imageResponse->body();
        $id = Str::uuid()->toString();

        return new ImageResult($id, $request->width ?? 1024, $request->height ?? 768, $rawBytes);
    }
}
