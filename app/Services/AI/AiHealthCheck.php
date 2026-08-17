<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AiHealthCheck
{
    /**
     * Checks if the required AI providers are currently responsive.
     * Caches the healthy status for a short period to prevent spamming pings on every job.
     */
    public function isHealthy(): bool
    {
        return Cache::remember('ai_health_status', 30, function () { // cache for 30 seconds
            return $this->pingOllama() && $this->pingGroq();
        });
    }

    protected function pingOllama(): bool
    {
        $url = config('services.ollama.url', env('OLLAMA_URL', 'http://127.0.0.1:11434')) . '/api/version';
        
        try {
            $response = Http::timeout(5)->get($url);
            if ($response->successful()) {
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("AI Health Check Failed: Ollama is unreachable. " . $e->getMessage());
            return false;
        }

        Log::warning("AI Health Check Failed: Ollama returned status " . $response->status());
        return false;
    }

    protected function pingGroq(): bool
    {
        $key = config('services.groq.api_key', env('GROQ_API_KEY', ''));
        if (empty($key)) {
            return true; // If not configured, assume we aren't relying on it, so don't block
        }

        try {
            // A lightweight ping to Groq models endpoint
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $key
            ])->timeout(5)->get('https://api.groq.com/openai/v1/models');
            
            if ($response->successful()) {
                return true;
            }
        } catch (\Exception $e) {
            Log::warning("AI Health Check Failed: Groq is unreachable. " . $e->getMessage());
            return false;
        }

        Log::warning("AI Health Check Failed: Groq returned status " . $response->status());
        return false;
    }
}
