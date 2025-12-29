<?php

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = config('services.gemini.api_key');

if (!$apiKey) {
    echo "Gemini API key not configured.\n";
    exit(1);
}

$model = env('GEMINI_IMAGE_MODEL', 'imagegeneration');

$endpoint = sprintf(
    'https://generativelanguage.googleapis.com/v1beta/models/%s:generate?key=%s',
    $model,
    $apiKey
);

/** @var \Illuminate\Http\Client\Response $response */
$response = Http::timeout(60)->post($endpoint, [
    'prompt' => [
        'text' => 'A simple blue square icon on white background',
    ],
]);

echo 'HTTP status: ' . $response->status() . PHP_EOL;
echo substr((string) $response->body(), 0, 800) . PHP_EOL;
