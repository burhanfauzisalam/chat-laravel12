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

$endpoint = sprintf(
    'https://generativelanguage.googleapis.com/v1/models?key=%s',
    $apiKey
);

/** @var \Illuminate\Http\Client\Response $response */
$response = Http::timeout(30)->get($endpoint);

echo 'HTTP status: ' . $response->status() . PHP_EOL;

$data = $response->json();

if (!isset($data['models']) || !is_array($data['models'])) {
    echo 'Response: ' . substr((string) $response->body(), 0, 400) . PHP_EOL;
    exit;
}

foreach (array_slice($data['models'], 0, 10) as $model) {
    $name = $model['name'] ?? '(no-name)';
    $supports = $model['supportedGenerationMethods'] ?? [];
    echo '- ' . $name . ' | methods: ' . implode(',', $supports) . PHP_EOL;
}

