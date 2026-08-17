<?php

namespace App\Services\AI;

use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class OllamaService implements LlmProvider
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('OLLAMA_URL', 'http://127.0.0.1:11434');
    }

    public function generate(string $model, string $prompt, array $options = []): array
    {
        try {
            $payload = [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ];

            if (isset($options['format'])) {
                $payload['format'] = $options['format'];
            }

            if (isset($options['options'])) {
                $payload['options'] = $options['options'];
            }

            $response = Http::timeout(600)
                ->retry(3, 1000)
                ->post("{$this->baseUrl}/api/generate", $payload);

            if ($response->failed()) {
                throw new AiGenerationException("Ollama API failed with status: {$response->status()}");
            }

            $data = $response->json();

            if (!isset($data['response'])) {
                throw new AiGenerationException("Unexpected response format from Ollama.");
            }

            return [
                'success' => true,
                'text' => $data['response'],
                'model' => $data['model'] ?? $model,
                'duration_ms' => isset($data['total_duration']) ? round($data['total_duration'] / 1000000) : null,
                'usage' => [
                    'prompt_tokens' => $data['prompt_eval_count'] ?? 0,
                    'completion_tokens' => $data['eval_count'] ?? 0,
                ],
            ];
        } catch (RequestException $e) {
            throw new AiGenerationException("Ollama connection failed: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof AiGenerationException) {
                throw $e;
            }
            throw new AiGenerationException("Ollama generation error: " . $e->getMessage(), 0, $e);
        }
    }
}
