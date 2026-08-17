<?php

namespace App\Services\AI;

interface WebSearchProvider
{
    /**
     * Search the web for a given query.
     * 
     * @param string $query The search query
     * @param int $limit Max results to return
     * @return array Array of arrays containing keys: 'url', 'title', 'snippet', 'content' (if fetched)
     */
    public function search(string $query, int $limit = 3): array;
}
