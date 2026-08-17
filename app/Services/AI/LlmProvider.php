<?php

namespace App\Services\AI;

interface LlmProvider
{
    /**
     * Generates text from the LLM based on the provided prompt.
     *
     * @param string $model
     * @param string $prompt
     * @param array $options
     * @return array Normalized result {success, text, model, duration_ms, usage}
     */
    public function generate(string $model, string $prompt, array $options = []): array;
}
