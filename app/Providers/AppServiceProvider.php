<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmProvider::class, function ($app) {
            $provider = config('services.llm.provider', 'ollama');

            if ($provider === 'groq') {
                return new GroqService(config('services.llm.groq.api_key'));
            }

            return new OllamaService();
        });
        $this->app->bind(\App\Services\AI\EmbeddingProvider::class, \App\Services\AI\EmbeddingService::class);
        $this->app->bind(\App\Services\AI\ImageProvider::class, \App\Services\AI\FalAiService::class);
        $this->app->bind(\App\Services\AI\SeoDataProvider::class, \App\Services\AI\DataForSeoService::class);
        $this->app->bind(\App\Services\AI\WebSearchProvider::class, \App\Services\AI\TavilySearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
