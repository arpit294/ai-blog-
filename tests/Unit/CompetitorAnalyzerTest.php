<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Automation\CompetitorAnalyzer;
use Illuminate\Support\Facades\Http;

class CompetitorAnalyzerTest extends TestCase
{
    public function test_extracts_topics_from_sitemap()
    {
        Http::fake([
            'example.com/robots.txt' => Http::response("User-agent: *\nAllow: /", 200),
            'example.com/sitemap.xml' => Http::response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://example.com/article1</loc></url></urlset>', 200),
            'example.com/article1' => Http::response('<html><head><title>Test Article Title | Example.com</title></head><body></body></html>', 200)
        ]);

        $analyzer = new CompetitorAnalyzer();
        $urls = $analyzer->fetchSitemap('https://example.com/sitemap.xml');
        $this->assertEquals(['https://example.com/article1'], $urls);

        $topics = $analyzer->extractTopics($urls);
        $this->assertEquals(['Test Article Title'], $topics);
    }
}
