<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DataForSeoService implements SeoDataProvider
{
    protected string $login;
    protected string $password;

    public function __construct()
    {
        $this->login = config('services.dataforseo.login', env('DATAFORSEO_LOGIN', ''));
        $this->password = config('services.dataforseo.password', env('DATAFORSEO_PASSWORD', ''));
    }

    public function fetchKeywords(string $niche, array $categories = []): array
    {
        if (empty($this->login) || empty($this->password)) {
            Log::warning("DataForSeo credentials missing. Returning empty keyword list.");
            return [];
        }

        try {
            $url = 'https://api.dataforseo.com/v3/dataforseo_labs/google/keyword_suggestions/live';
            
            $payload = [
                [
                    "keyword" => $niche,
                    "location_name" => "United States",
                    "language_name" => "English",
                    "include_serp_info" => false,
                    "limit" => 50,
                    "filters" => [
                        ["search_volume", ">", 100],
                        "and",
                        ["competition", "<", 80] // DataForSeo competition is 0-100 (where >80 is High)
                    ],
                    "order_by" => ["search_volume,desc"]
                ]
            ];

            $response = Http::withBasicAuth($this->login, $this->password)
                ->timeout(30)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::error("DataForSeo API failed: " . $response->body());
                return [];
            }

            $data = $response->json();
            
            $results = [];
            if (isset($data['tasks'][0]['result'][0]['items'])) {
                foreach ($data['tasks'][0]['result'][0]['items'] as $item) {
                    $results[] = [
                        'keyword' => $item['keyword'],
                        'search_volume' => $item['keyword_info']['search_volume'] ?? 0,
                        'competition_score' => $item['keyword_info']['competition'] ?? 0,
                    ];
                }
            }

            return $results;

        } catch (\Exception $e) {
            Log::error("DataForSeo exception: " . $e->getMessage());
            return [];
        }
    }
}
