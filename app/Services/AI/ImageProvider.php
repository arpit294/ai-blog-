<?php

namespace App\Services\AI;

interface ImageProvider
{
    public function generate(ImageRequest $request): ImageResult;
}
