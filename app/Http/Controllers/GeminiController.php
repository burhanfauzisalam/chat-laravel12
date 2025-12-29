<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeminiController extends Controller
{
    public function chat(Request $request)
    {
        $user = $request->user();
        $currentUsername = $user?->username ?? null;

        $data = $request->validate([
            'topic' => ['required', 'string'],
        ]);

        $topic = $data['topic'];

        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (in_array($model, ['gemini-1.5-flash', 'gemini-1.5-flash-latest'], true)) {
            $model = 'gemini-2.5-flash';
        }

        if (!$apiKey) {
            return response()->json([
                'message' => 'Gemini API key is not configured.',
            ], 500);
        }

        $messagesQuery = Message::where('topic', $topic);

        if ($currentUsername) {
            $messagesQuery->where(function ($query) use ($currentUsername) {
                $query
                    ->where('sender', $currentUsername)
                    ->orWhere('sender', 'Gemini@' . $currentUsername);
            });
        }

        $messages = $messagesQuery
            ->orderByDesc('id')
            ->take(20)
            ->get()
            ->reverse()
            ->values();

        if ($messages->isEmpty()) {
            return response()->json([
                'message' => 'No messages found for this topic.',
            ], 422);
        }

        $latestMessage = $messages->last();
        $latestPdfHasNoText = false;

        $contents = $messages
            ->map(function (Message $message) use ($currentUsername, $latestMessage, &$latestPdfHasNoText) {
                $isModel = $currentUsername
                    ? $message->sender === 'Gemini@' . $currentUsername
                    : $message->sender === 'Gemini';

                $role = $isModel ? 'model' : 'user';

                $parts = [];

                $text = (string) ($message->text ?? '');
                if ($text !== '') {
                    $parts[] = [
                        'text' => $text,
                    ];
                }

                if ($message->attachment_path && $message->attachment_type && !$isModel) {
                    try {
                        $disk = Storage::disk('public');

                        if ($disk->exists($message->attachment_path)) {
                            $mimeType = $message->attachment_type;
                            $isLatest = $latestMessage && $message->id === $latestMessage->id;

                            if (Str::startsWith($mimeType, 'image/')) {
                                $binary = $disk->get($message->attachment_path);

                                if ($binary !== null && $binary !== false) {
                                    $parts[] = [
                                        'inlineData' => [
                                            'mimeType' => $mimeType,
                                            'data' => base64_encode($binary),
                                        ],
                                    ];
                                }
                            } elseif ($mimeType === 'application/pdf' || Str::contains($mimeType, 'pdf')) {
                                $attachmentText = null;

                                if (class_exists(\Smalot\PdfParser\Parser::class)) {
                                    $parser = new \Smalot\PdfParser\Parser();
                                    $pdf = $parser->parseContent($disk->get($message->attachment_path));
                                    $textFromPdf = $pdf->getText();

                                    if (is_string($textFromPdf) && $textFromPdf !== '') {
                                        $attachmentText = trim($textFromPdf);

                                        if (strlen($attachmentText) > 4000) {
                                            $attachmentText = substr($attachmentText, 0, 4000) . "\n\n[Konten PDF dipotong untuk menjaga batas konteks]";
                                        }
                                    } elseif ($isLatest) {
                                        $latestPdfHasNoText = true;
                                    }
                                } elseif ($isLatest) {
                                    $latestPdfHasNoText = true;
                                }

                                if ($attachmentText) {
                                    $label = $message->attachment_name ?: 'file PDF';
                                    $parts[] = [
                                        'text' => "Ini adalah teks yang diekstrak dari {$label}:\n\n" . $attachmentText,
                                    ];
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }

                if (empty($parts)) {
                    $parts[] = [
                        'text' => '',
                    ];
                }

                return [
                    'role' => $role,
                    'parts' => $parts,
                ];
            })
            ->all();

        if ($latestPdfHasNoText) {
            return response()->json([
                'message' => 'File PDF tidak memiliki teks yang bisa dibaca. Silakan unggah sebagai gambar atau gunakan PDF yang berisi teks.',
                'details' => [
                    'reason' => 'pdf_no_text',
                    'topic' => $topic,
                ],
            ], 422);
        }

        $styleInstruction = 'Kamu adalah asisten AI di dalam aplikasi chat. '
            . 'Jawab dengan bahasa yang rapi dan singkat. '
            . 'Jangan gunakan heading atau markdown (seperti #, ##, **, ```). '
            . 'Convert hasil kedalam bentuk HTML tag. '
            . 'Selalu balas dalam bahasa yang sama dengan pesan pengguna, default Bahasa Indonesia.';

        array_unshift($contents, [
            'role' => 'user',
            'parts' => [
                [
                    'text' => $styleInstruction,
                ],
            ],
        ]);

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model,
        );

        /** @var HttpClientResponse $response */
        $response = Http::timeout(30)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($endpoint, [
                'contents' => $contents,
            ]);

        if (!$response->successful()) {
            $body = $response->json();

            $errorMessage = is_array($body)
                ? ($body['error']['message'] ?? $body['message'] ?? 'Failed to call Gemini API.')
                : 'Failed to call Gemini API.';

            return response()->json([
                'message' => $errorMessage,
                'details' => $body,
            ], $response->status() ?: 500);
        }

        $data = $response->json();

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!is_string($text) || $text === '') {
            return response()->json([
                'message' => 'Gemini API returned an empty response.',
            ], 500);
        }

        $sender = $currentUsername ? 'Gemini@' . $currentUsername : 'Gemini';

        $message = Message::create([
            'sender' => $sender,
            'text' => $text,
            'topic' => $topic,
        ]);

        $avatarUrl = asset('assets/img/avatars/2.png');

        return response()->json([
            'id' => $message->id,
            'sender' => $sender,
            'text' => $message->text,
            'topic' => $message->topic,
            'created_at' => $message->created_at,
            'attachment_url' => null,
            'attachment_name' => null,
            'attachment_type' => null,
            'attachment_size' => null,
            'avatar_url' => $avatarUrl,
        ]);
    }
}
