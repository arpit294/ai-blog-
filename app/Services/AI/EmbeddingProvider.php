<?php

namespace App\Services\AI;

interface EmbeddingProvider
{
    /**
     * @return array The vector array
     */
    public function embed(string $text): array;
}
