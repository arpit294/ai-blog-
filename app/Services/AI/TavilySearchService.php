<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TavilySearchService implements WebSearchProvider
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.tavily.key', env('TAVILY_API_KEY', ''));
    }

    public function search(string $query, int $limit = 3): array
    {
        if (empty($this->apiKey)) {
            Log::warning("Tavily API key missing. Returning empty search results.");
            return [];
        }

        try {
            $url = 'https://api.tavily.com/search';
            
            $payload = [
                'api_key' => $this->apiKey,
                'query' => $query,
                'search_depth' => 'advanced',
                'include_raw_content' => true, // Fetch actual content if possible
                'max_results' => $limit,
            ];

            $response = Http::timeout(30)->post($url, $payload);

            if ($response->failed()) {
                Log::error("Tavily API failed: " . $response->body());
                return [];
            }

            $data = $response->json();
            
            $results = [];
            if (isset($data['results']) && is_array($data['results'])) {
                foreach ($data['results'] as $item) {
                    $results[] = [
                        'url' => $item['url'] ?? '',
                        'title' => $item['title'] ?? '',
                        'snippet' => $item['content'] ?? '', // Tavily calls snippet 'content'
                        'raw_content' => $item['raw_content'] ?? null,
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("Tavily exception: " . $e->getMessage());
            return [];
        }
    }
}
