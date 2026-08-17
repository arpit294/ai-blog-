<?php

namespace App\Services\AI;

interface SeoDataProvider
{
    /**
     * Fetch a list of keyword candidates based on a niche and optional categories.
     * 
     * @param string $niche The main niche (e.g. "personal finance")
     * @param array $categories Array of specific categories to target
     * @return array Array of arrays containing keys: 'keyword', 'search_volume', 'competition_score'
     */
    public function fetchKeywords(string $niche, array $categories = []): array;
}
