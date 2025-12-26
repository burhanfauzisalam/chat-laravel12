<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HistoryMessageController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        $currentUsername = $user->username ?? null;
        $geminiTopic = config('services.gemini.topic');

        $data = $request->validate([
            'topic' => ['required', 'string'],
            'before_id' => ['nullable', 'integer'],
        ]);

        $topic = $data['topic'];

        // Pastikan user memang memiliki akses ke room/topik ini
        $hasRoom = $user->rooms()->where('topic', $topic)->exists();
        if (!$hasRoom) {
            abort(403);
        }

        $perPage = 50;

        $query = Message::where('topic', $topic);

        if ($topic === $geminiTopic && $currentUsername) {
            $query->where(function ($q) use ($currentUsername) {
                $q
                    ->where('sender', $currentUsername)
                    ->orWhere('sender', 'Gemini@' . $currentUsername);
            });
        }

        if (!empty($data['before_id'])) {
            $query->where('id', '<', $data['before_id']);
        }

        $messagesDesc = $query
            ->orderBy('id', 'desc')
            ->take($perPage)
            ->get(['id', 'sender', 'text', 'topic', 'created_at', 'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size']);

        $messages = $messagesDesc->sortBy('id')->values();

        $hasMore = false;
        $nextBeforeId = null;

        if ($messages->isNotEmpty()) {
            $oldestId = $messages->first()->id;

            $moreQuery = Message::where('topic', $topic)
                ->where('id', '<', $oldestId);

            if ($topic === $geminiTopic && $currentUsername) {
                $moreQuery->where(function ($q) use ($currentUsername) {
                    $q
                        ->where('sender', $currentUsername)
                        ->orWhere('sender', 'Gemini@' . $currentUsername);
                });
            }

            $hasMore = $moreQuery->exists();
            $nextBeforeId = $oldestId;
        }

        // Tambahkan avatar_url untuk setiap pesan berdasarkan username pengirim
        if ($messages->isNotEmpty()) {
            $senders = $messages->pluck('sender')->filter()->unique()->values();

            if ($senders->isNotEmpty()) {
                $users = User::whereIn('username', $senders)->get();
                $avatarsByUsername = $users
                    ->mapWithKeys(function (User $u) {
                        return [$u->username => $u->avatar_url];
                    })
                    ->all();

                $defaultAvatar = asset('assets/img/avatars/1.png');

                $messages->transform(function (Message $msg) use ($avatarsByUsername, $defaultAvatar) {
                    $msg->avatar_url = $avatarsByUsername[$msg->sender] ?? $defaultAvatar;
                    return $msg;
                });
            }
        }

        return response()->json([
            'messages' => $messages->map(function (Message $msg) {
                return [
                    'id' => $msg->id,
                    'sender' => $msg->sender,
                    'text' => $msg->text,
                    'topic' => $msg->topic,
                    'created_at' => $msg->created_at?->toIso8601String(),
                    'attachment_url' => $msg->attachment_url,
                    'attachment_name' => $msg->attachment_name,
                    'attachment_type' => $msg->attachment_type,
                    'attachment_size' => $msg->attachment_size,
                    'avatar_url' => $msg->avatar_url ?? null,
                ];
            }),
            'has_more' => $hasMore,
            'next_before_id' => $nextBeforeId,
        ]);
    }
}
