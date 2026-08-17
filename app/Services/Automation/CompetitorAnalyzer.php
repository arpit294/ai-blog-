<?php

namespace App\Services\Automation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class CompetitorAnalyzer
{
    /**
     * Fetch and parse the competitor's sitemap.xml.
     * Extracts all article URLs, handling sitemap index files if necessary.
     */
    public function fetchSitemap(string $url): array
    {
        $robotsUrl = rtrim(parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST), '/') . '/robots.txt';
        
        // Simple robots.txt check (very basic)
        try {
            $robots = Http::timeout(10)->get($robotsUrl);
            if ($robots->successful() && stripos($robots->body(), "Disallow: /") !== false && stripos($robots->body(), "User-agent: *") !== false) {
                Log::info("Skipped competitor due to robots.txt: {$url}");
                return [];
            }
        } catch (\Exception $e) {
            // Ignore robots failure, assume allowed if we can't reach it
        }

        try {
            $response = Http::timeout(20)->get($url);
            if ($response->failed()) {
                return [];
            }

            $xmlString = $response->body();
            $xml = @simplexml_load_string($xmlString);

            if (!$xml) {
                return [];
            }

            $urls = [];

            // Check if it's a sitemap index
            if (isset($xml->sitemap)) {
                foreach ($xml->sitemap as $sitemap) {
                    $urls = array_merge($urls, $this->fetchSitemap((string) $sitemap->loc));
                }
            } elseif (isset($xml->url)) {
                foreach ($xml->url as $urlNode) {
                    $urls[] = (string) $urlNode->loc;
                }
            }

            return $urls;
        } catch (\Exception $e) {
            Log::error("Failed to parse sitemap {$url}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Extract topic signals from a list of URLs (by fetching title tags).
     */
    public function extractTopics(array $urls, int $limit = 10): array
    {
        $topics = [];
        $urls = array_slice($urls, 0, $limit); // Limit to avoid massive crawling

        foreach ($urls as $url) {
            try {
                $response = Http::timeout(10)->get($url);
                if ($response->successful()) {
                    if (preg_match('/<title>(.*?)<\/title>/is', $response->body(), $matches)) {
                        $title = trim(strip_tags($matches[1]));
                        // Clean up generic suffixes like "- BrandName"
                        $title = preg_replace('/ - [^-]+$/', '', $title);
                        $title = preg_replace('/ \| [^|]+$/', '', $title);
                        if (!empty($title)) {
                            $topics[] = $title;
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Failed to extract title from {$url}");
            }
        }

        return array_unique($topics);
    }
}
