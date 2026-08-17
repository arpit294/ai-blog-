<?php

namespace App\Services\AI;

class ImageRequest
{
    public string $prompt;
    public ?int $width;
    public ?int $height;

    public function __construct(string $prompt, ?int $width = 1024, ?int $height = 1024)
    {
        $this->prompt = $prompt;
        $this->width = $width;
        $this->height = $height;
    }
}
