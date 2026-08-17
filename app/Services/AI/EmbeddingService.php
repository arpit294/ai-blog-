<?php

namespace App\Services\AI;

use App\Exceptions\AiGenerationException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class EmbeddingService implements EmbeddingProvider
{
    protected string $baseUrl;
    public string $model;

    public function __construct()
    {
        $this->baseUrl = env('OLLAMA_URL', 'http://127.0.0.1:11434');
        $this->model = env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text');
    }

    public function embed(string $text): array
    {
        try {
            $payload = [
                'model' => $this->model,
                'prompt' => $text,
            ];

            $response = Http::timeout(60)
                ->retry(3, 1000)
                ->post("{$this->baseUrl}/api/embeddings", $payload);

            if ($response->failed()) {
                throw new AiGenerationException("Ollama Embeddings API failed with status: {$response->status()}");
            }

            $data = $response->json();

            if (!isset($data['embedding'])) {
                throw new AiGenerationException("Unexpected response format from Ollama Embeddings.");
            }

            return $data['embedding'];
        } catch (RequestException $e) {
            throw new AiGenerationException("Ollama embeddings connection failed: " . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            if ($e instanceof AiGenerationException) {
                throw $e;
            }
            throw new AiGenerationException("Ollama embeddings error: " . $e->getMessage(), 0, $e);
        }
    }
}
