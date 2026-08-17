<?php

namespace App\Services\AI;

class ImageResult
{
    public string $provider_generation_id;
    public int $width;
    public int $height;
    public string $raw_bytes;

    public function __construct(string $id, int $width, int $height, string $rawBytes)
    {
        $this->provider_generation_id = $id;
        $this->width = $width;
        $this->height = $height;
        $this->raw_bytes = $rawBytes;
    }
}
