<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$profile = \App\Models\AutomationProfile::first();
$topic = \App\Models\BlogTopic::create([
    'automation_id' => $profile->id,
    'title' => 'The Future of AI Technology',
    'normalized_title' => 'the future of ai technology',
    'summary' => 'A look into the future.',
    'category' => 'Technology',
    'intent' => 'informational',
    'primary_keyword' => 'AI',
    'status' => 'used',
    'source_run_id' => 1
]);

$run = \App\Models\AutomationRun::create([
    'automation_profile_id' => $profile->id,
    'status' => 'running',
    'current_stage' => 'content_generation',
    'run_key' => 'test_gen_'.time()
]);

echo "Starting generation...\n";
$generator = app(\App\Services\Automation\ContentGenerator::class);
try {
    $article = $generator->generate($profile, $topic, $run->id);
    file_put_contents('test_gen_result.json', json_encode($article, JSON_PRETTY_PRINT));
    echo "Done! Wrote to test_gen_result.json\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
