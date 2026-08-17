<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Generation Retry Budget
    |--------------------------------------------------------------------------
    | Maximum number of times a job can fail and trigger a retry (due to
    | invalid JSON output, network timeouts, or regenerable quality checks).
    */
    'retry_budget' => 3,

    /*
    |--------------------------------------------------------------------------
    | Quality Checker Weights
    |--------------------------------------------------------------------------
    | The weighted distribution for the overall_score.
    */
    'quality_weights' => [
        'structure' => 0.20,
        'completeness' => 0.20,
        'seo' => 0.15,
        'readability' => 0.15,
        'uniqueness' => 0.15,
        'technical_validity' => 0.15,
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Provider Tiering
    |--------------------------------------------------------------------------
    | Mapping of generation stage to the preferred LLM provider.
    | Valid providers: 'ollama', 'groq'
    */
    'ai_providers' => [
        'outline_generation' => 'ollama',
        'section_content_generation' => 'groq',
        'fact_extraction' => 'groq',
        'seo_generation' => 'ollama',
    ],
];
