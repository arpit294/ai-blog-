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
        $this->app->bind(\App\Services\AI\LlmProvider::class, \App\Services\AI\OllamaService::class);
        $this->app->bind(\App\Services\AI\EmbeddingProvider::class, \App\Services\AI\EmbeddingService::class);
        $this->app->bind(\App\Services\AI\ImageProvider::class, \App\Services\AI\LocalImageProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
