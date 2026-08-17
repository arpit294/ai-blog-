<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$errors = \DB::table('failed_jobs')->orderBy('id', 'desc')->take(3)->get();
foreach($errors as $err) {
    echo "ID: {$err->id}\n" . substr($err->exception, 0, 300) . "\n\n";
}
