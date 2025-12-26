<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        $contents = $messages
            ->map(function (Message $message) use ($currentUsername) {
                $isModel = false;
                if ($currentUsername) {
                    $isModel = $message->sender === 'Gemini@' . $currentUsername;
                } else {
                    $isModel = $message->sender === 'Gemini';
                }

                $role = $isModel ? 'model' : 'user';

                return [
                    'role' => $role,
                    'parts' => [
                        [
                            'text' => $message->text,
                        ],
                    ],
                ];
            })
            ->all();

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
