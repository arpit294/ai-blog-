<?php

namespace App\Services\AI;

use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class GroqService implements LlmProvider
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct(string $apiKey = '')
    {
        $this->baseUrl = 'https://api.groq.com/openai/v1/chat/completions';
        $this->apiKey = $apiKey ?: (config('services.llm.groq.api_key') ?? '');
    }

    public function generate(string $model, string $prompt, array $options = []): array
    {
        if (empty($this->apiKey) || $this->apiKey === 'gsk_REPLACE_WITH_YOUR_KEY' || $this->apiKey === 'gsk_YOUR_API_KEY_HERE') {
            throw new AiGenerationException("Groq API key is missing or invalid. Please update your .env file.");
        }

        try {
            $payload = [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'stream' => false,
            ];

            if (isset($options['format']) && $options['format'] === 'json') {
                $payload['response_format'] = ['type' => 'json_object'];
                // Groq requires 'json' in the prompt when using json_object
                if (stripos($prompt, 'json') === false) {
                    $payload['messages'][0]['content'] .= "\nOutput strictly as JSON.";
                }
            }

            if (isset($options['options'])) {
                if (isset($options['options']['temperature'])) {
                    $payload['temperature'] = $options['options']['temperature'];
                }
            }

            $start = microtime(true);
            $response = Http::withToken($this->apiKey)
                ->timeout(60)
                ->retry(3, 1000)
                ->post($this->baseUrl, $payload);

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error("LLM Provider failed. URL: {$this->baseUrl}, Key Prefix: " . substr($this->apiKey, 0, 5) . ", Model: {$model}");
                throw new AiGenerationException("Groq API failed with status {$response->status()}: {$response->body()}");
            }

            $data = $response->json();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new AiGenerationException("Unexpected response format from Groq.");
            }

            return [
                'success' => true,
                'text' => $data['choices'][0]['message']['content'],
                'model' => $data['model'] ?? $model,
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'usage' => [
                    'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                ],
            ];
        } catch (RequestException $e) {
            throw new AiGenerationException("Groq connection failed: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof AiGenerationException) {
                throw $e;
            }
            throw new AiGenerationException("Groq generation error: " . $e->getMessage(), 0, $e);
        }
    }
}
