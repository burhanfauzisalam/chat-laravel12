<?php

require __DIR__ . '/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Message;
use Illuminate\Support\Facades\Storage;

$message = Message::whereNotNull('attachment_path')
    ->orderByDesc('id')
    ->first();

if (!$message) {
    echo "No message with attachment found.\n";
    exit(0);
}

echo "ID: {$message->id}\n";
echo "Topic: {$message->topic}\n";
echo "Sender: {$message->sender}\n";
echo "Attachment path: {$message->attachment_path}\n";
echo "Attachment name: {$message->attachment_name}\n";
echo "Attachment type: {$message->attachment_type}\n";
echo "Attachment size: {$message->attachment_size}\n";

$disk = Storage::disk('public');
$exists = $disk->exists($message->attachment_path);
echo "Exists on disk: " . ($exists ? 'yes' : 'no') . "\n";

if ($exists && $message->attachment_type && str_contains($message->attachment_type, 'pdf')) {
    echo "Trying to parse PDF...\n";

    try {
        $parser = new \Smalot\PdfParser\Parser();
        $binary = $disk->get($message->attachment_path);
        $pdf = $parser->parseContent($binary);
        $text = $pdf->getText();
        $len = is_string($text) ? strlen($text) : 0;

        echo "Extracted text length: {$len}\n";
        if ($len > 0) {
            echo "First 800 chars:\n";
            echo substr($text, 0, 800) . "\n";
        }
    } catch (\Throwable $e) {
        echo "PDF parse error: " . $e->getMessage() . "\n";
    }
}

