<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LocalImageProvider implements ImageProvider
{
    public function generate(ImageRequest $request): ImageResult
    {
        $url = config('services.local_image.url', 'http://127.0.0.1:8188/generate');
        
        $response = Http::timeout(60)->post($url, [
            'prompt' => $request->prompt,
            'width' => $request->width,
            'height' => $request->height,
        ]);

        if ($response->failed()) {
            throw new \Exception("Local image generation failed: " . $response->body());
        }

        $rawBytes = $response->body();
        $id = $response->header('X-Generation-Id') ?? Str::uuid()->toString();

        return new ImageResult($id, $request->width ?? 1024, $request->height ?? 1024, $rawBytes);
    }
}
