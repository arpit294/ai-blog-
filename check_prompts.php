<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$profile = \App\Models\AutomationProfile::first();
$topic = \App\Models\BlogTopic::where('title', 'The Future of AI Technology')->first();

$ps = app(\App\Services\AI\PromptService::class);

$headings = [
    "AI's Evolutionary Leap: The Future of Artificial Intelligence",
    "The Rise of AI-Powered Applications: Revolutionizing Industries and Societies"
];

foreach ($headings as $i => $heading) {
    echo "=== SECTION $i ===\n";
    echo $ps->buildPrompt('section', $profile, $topic, [
        'section' => $heading,
        'outline' => json_encode($headings)
    ]);
    echo "\n\n";
}
