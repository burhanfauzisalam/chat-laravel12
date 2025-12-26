<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GroqController extends Controller
{
    public function chat(Request $request)
    {
        $data = $request->validate([
            'topic' => ['required', 'string'],
        ]);

        $topic = $data['topic'];

        $apiKey = config('services.groq.api_key');
        $model = config('services.groq.model', 'llama-3.1-8b-instant');

        if (!$apiKey) {
            return response()->json([
                'message' => 'Groq API key is not configured.',
            ], 500);
        }

        $messages = Message::where('topic', $topic)
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

        $chatMessages = [];

        $chatMessages[] = [
            'role' => 'system',
            'content' => 'You are Groq, an AI assistant integrated into a chat application. Answer concisely in the same language as the user, default to Indonesian.',
        ];

        foreach ($messages as $message) {
            $role = $message->sender === 'Groq' ? 'assistant' : 'user';

            $chatMessages[] = [
                'role' => $role,
                'content' => $message->text,
            ];
        }

        /** @var HttpClientResponse $response */
        $response = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $model,
                'messages' => $chatMessages,
            ]);

        if (!$response->successful()) {
            $body = $response->json();

            $errorMessage = is_array($body)
                ? ($body['error']['message'] ?? $body['message'] ?? 'Failed to call Groq API.')
                : 'Failed to call Groq API.';

            return response()->json([
                'message' => $errorMessage,
                'details' => $body,
            ], $response->status() ?: 500);
        }

        $data = $response->json();

        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($text) || $text === '') {
            return response()->json([
                'message' => 'Groq API returned an empty response.',
            ], 500);
        }

        $sender = 'Groq';

        $message = Message::create([
            'sender' => $sender,
            'text' => $text,
            'topic' => $topic,
        ]);

        $avatarUrl = asset('assets/img/avatars/4.png');

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

