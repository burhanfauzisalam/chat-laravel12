<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'text' => ['nullable', 'string'],
            'topic' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:5120'], // max ~5MB
        ]);

        if (empty($data['text']) && !$request->hasFile('file')) {
            return response()->json([
                'message' => 'Text atau file wajib diisi.',
            ], 422);
        }

        $sender = $request->user()->username ?? 'User';

        $attachmentPath = null;
        $attachmentName = null;
        $attachmentType = null;
        $attachmentSize = null;

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $attachmentPath = $file->store('chat-attachments', 'public');
            $attachmentName = $file->getClientOriginalName();
            $attachmentType = $file->getClientMimeType();
            $attachmentSize = $file->getSize();
        }

        $message = Message::create([
            'sender' => $sender,
            'text' => $data['text'] ?? '',
            'topic' => $data['topic'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_type' => $attachmentType,
            'attachment_size' => $attachmentSize,
        ]);

        return response()->json([
            'id' => $message->id,
            'sender' => $message->sender,
            'text' => $message->text,
            'topic' => $message->topic,
            'created_at' => $message->created_at,
            'attachment_url' => $message->attachment_url,
            'attachment_name' => $message->attachment_name,
            'attachment_type' => $message->attachment_type,
            'attachment_size' => $message->attachment_size,
            'avatar_url' => $request->user()->avatar_url,
        ]);
    }
}

