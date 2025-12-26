<?php

// Simple CLI script to test Gemini API connectivity using the current .env.

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
}

$apiKey = getenv('GEMINI_API_KEY') ?: '';
$model = getenv('GEMINI_MODEL') ?: 'gemini-1.5-flash';

if ($apiKey === '') {
    fwrite(STDERR, "GEMINI_API_KEY is not set\n");
    exit(1);
}

$body = json_encode([
    'contents' => [
        [
            'role' => 'user',
            'parts' => [
                ['text' => 'Hello from CLI Gemini connectivity test.'],
            ],
        ],
    ],
], JSON_UNESCAPED_SLASHES);

$url = sprintf(
    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
    $model,
    $apiKey
);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);

$out = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP {$code}\n";
if ($err) {
    echo "cURL error: {$err}\n";
}

echo substr((string) $out, 0, 400), "\n";

