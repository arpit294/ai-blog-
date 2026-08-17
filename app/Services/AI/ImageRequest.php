<?php

namespace App\Services\AI;

class ImageRequest
{
    public string $prompt;
    public ?int $width;
    public ?int $height;
    public string $aspectRatio;
    public ?string $lora;

    public function __construct(string $prompt, ?int $width = 1024, ?int $height = 1024, string $aspectRatio = '16:9', ?string $lora = null)
    {
        $this->prompt = $prompt;
        $this->width = $width;
        $this->height = $height;
        $this->aspectRatio = $aspectRatio;
        $this->lora = $lora;
    }
}
