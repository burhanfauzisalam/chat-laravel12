<?php

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$apiKey = config('services.gemini.api_key');
$model = config('services.gemini.model', 'gemini-2.0-flash');

if (in_array($model, ['gemini-1.5-flash', 'gemini-1.5-flash-latest'], true)) {
    $model = 'gemini-2.0-flash';
}

if (!$apiKey) {
    echo "Gemini API key not configured.\n";
    exit(1);
}

$contents = [
    [
        'role' => 'user',
        'parts' => [
            ['text' => 'Hello from Laravel Http test.'],
        ],
    ],
];

$endpoint = sprintf(
    'https://generativelanguage.googleapis.com/v1/models/%s:generateContent?key=%s',
    $model,
    $apiKey
);

/** @var \Illuminate\Http\Client\Response $response */
$response = Http::timeout(30)->post($endpoint, [
    'contents' => $contents,
]);

echo 'HTTP status: ' . $response->status() . PHP_EOL;
echo 'Body (first 400 chars): ' . substr((string) $response->body(), 0, 400) . PHP_EOL;
