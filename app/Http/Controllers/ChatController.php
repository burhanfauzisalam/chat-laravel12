<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $mqttConfig = config('services.mqtt');
        $user = $request->user();
        $currentUser = $user?->username ?? 'Guest';

        $rooms = $user
            ? $user->rooms()->withCount('users')->orderBy('id')->get()
            : collect();

        // Jika berpindah room, tandai room sebelumnya sebagai sudah dibaca
        $markReadTopic = $request->query('mark_read');
        if ($user && $markReadTopic && $rooms->isNotEmpty()) {
            $roomToMark = $rooms->firstWhere('topic', $markReadTopic);
            if ($roomToMark) {
                $now = now();
                $user->rooms()->updateExistingPivot($roomToMark->id, [
                    'last_read_at' => $now,
                ]);
                $roomToMark->pivot->last_read_at = $now;
            }
        }

        $requestedTopic = $request->query('topic');
        $activeRoom = null;

        if ($requestedTopic && $rooms->isNotEmpty()) {
            $activeRoom = $rooms->firstWhere('topic', $requestedTopic);
        }

        if (!$activeRoom && $rooms->isNotEmpty()) {
            $activeRoom = $rooms->first();
        }

        $activeTopic = $activeRoom?->topic;

        if ($activeRoom) {
            $activeRoom->loadMissing('users');
        }

        // Kumpulkan avatar semua user di rooms yang diikuti
        $userAvatars = [];
        if ($user && $rooms->isNotEmpty()) {
            $rooms->loadMissing('users');

            $allUsers = $rooms
                ->flatMap(function (Room $room) {
                    return $room->users;
                })
                ->push($user)
                ->unique('id');

            $userAvatars = $allUsers
                ->mapWithKeys(function (User $u) {
                    return [$u->username => $u->avatar_url];
                })
                ->all();
        }
        $rawLastReadAt = $activeRoom?->pivot?->last_read_at;
        $activeLastReadAt = $rawLastReadAt
            ? Carbon::parse($rawLastReadAt)->toIso8601String()
            : null;

        $messages = collect();
        $hasMoreHistory = false;
        $oldestMessageId = null;

        if ($activeTopic) {
            $perPage = 50;

            $baseQuery = Message::where('topic', $activeTopic)
                ->orderBy('id', 'desc');

            $pageMessagesDesc = $baseQuery
                ->take($perPage)
                ->get(['id', 'sender', 'text', 'topic', 'created_at', 'attachment_path', 'attachment_name', 'attachment_type', 'attachment_size']);

            $messages = $pageMessagesDesc->sortBy('id')->values();

            if ($messages->isNotEmpty()) {
                $oldestMessageId = $messages->first()->id;
                $hasMoreHistory = Message::where('topic', $activeTopic)
                    ->where('id', '<', $oldestMessageId)
                    ->exists();
            }
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

        // Hitung jumlah pesan belum dibaca per room untuk user ini
        if ($user && $rooms->isNotEmpty()) {
            foreach ($rooms as $room) {
                // Untuk room yang sedang aktif, badge tidak ditampilkan
                if ($activeTopic && $room->topic === $activeTopic) {
                    $room->unread_count = 0;
                    continue;
                }

                $lastReadAt = $room->pivot->last_read_at ?? null;

                $query = Message::where('topic', $room->topic);
                if ($lastReadAt) {
                    $query->where('created_at', '>', $lastReadAt);
                }

                // Selaras dengan frontend: hanya hitung pesan dari user lain
                if ($currentUser) {
                    $query->where('sender', '!=', $currentUser);
                }

                $room->unread_count = $query->count();
            }
        }

        // Saat room aktif dibuka, tandai semua pesan sebagai sudah dibaca
        if ($user && $activeRoom) {
            $user->rooms()->updateExistingPivot($activeRoom->id, [
                'last_read_at' => now(),
            ]);
            // Pastikan badge di UI untuk room aktif langsung 0
            $activeRoom->unread_count = 0;
        }

        return view('chat', [
            'mqttConfig' => $mqttConfig,
            'currentUser' => $currentUser,
            'messages' => $messages,
            'rooms' => $rooms,
            'activeTopic' => $activeTopic,
            'activeRoom' => $activeRoom,
            'activeLastReadAt' => $activeLastReadAt,
            'userAvatars' => $userAvatars,
            'hasMoreHistory' => $hasMoreHistory,
            'oldestMessageId' => $oldestMessageId,
        ]);
    }
}
