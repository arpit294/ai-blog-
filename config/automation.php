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
    ]
];
