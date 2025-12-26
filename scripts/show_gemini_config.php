<?php

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'env(GEMINI_API_KEY): ' . (env('GEMINI_API_KEY') ? 'SET' : 'EMPTY') . PHP_EOL;
echo 'env(GEMINI_MODEL): ' . (env('GEMINI_MODEL') ?: '(none)') . PHP_EOL;
echo 'env(GEMINI_TOPIC): ' . (env('GEMINI_TOPIC') ?: '(none)') . PHP_EOL;

$config = config('services.gemini');

echo 'config(services.gemini): ' . json_encode($config, JSON_PRETTY_PRINT) . PHP_EOL;

