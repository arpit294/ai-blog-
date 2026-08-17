<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$runId = 1; // Assuming our test run
$generations = \App\Models\AiGeneration::where('run_id', \App\Models\AutomationRun::latest()->first()->id)->with('logs')->get();

foreach ($generations as $g) {
    echo "========================================\n";
    echo "STAGE: " . $g->task_type . "\n";
    echo "---------------- PROMPT ----------------\n";
    // We didn't save the exact prompt in AiGeneration, wait. Did we?
    // Let's check the schema for AiGeneration to see if prompt is saved.
    // If not, we only have the output payload_ref.
    
    echo "---------------- OUTPUT ----------------\n";
    echo $g->logs->first()->payload_ref . "\n";
}
