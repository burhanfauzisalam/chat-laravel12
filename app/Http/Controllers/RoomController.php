<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'room_name' => ['required', 'string', 'max:100'],
            'room_topic' => [
                'required',
                'string',
                'max:191',
                Rule::unique('rooms', 'topic'),
            ],
        ]);

        $room = Room::create([
            'name' => $data['room_name'],
            'topic' => $data['room_topic'],
        ]);

        // Auto join creator to the new room
        $user->rooms()->syncWithoutDetaching([
            $room->id => ['last_read_at' => now()],
        ]);

        return redirect()->route('chat.index', ['topic' => $room->topic]);
    }

    public function join(Request $request, Room $room)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $user->rooms()->syncWithoutDetaching([
            $room->id => ['last_read_at' => now()],
        ]);

        return redirect()->route('chat.index', ['topic' => $room->topic]);
    }

    public function leave(Request $request, Room $room)
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->rooms()->whereKey($room->id)->doesntExist()) {
            return redirect()->route('chat.index');
        }

        $user->rooms()->detach($room->id);

        $nextRoom = $user->rooms()->orderBy('id')->first();

        // Jika tidak ada user lain di room ini, hapus room (dan pesannya)
        if ($room->users()->count() === 0) {
            Message::where('topic', $room->topic)->delete();
            $room->delete();
        }

        if ($nextRoom) {
            return redirect()->route('chat.index', ['topic' => $nextRoom->topic]);
        }

        return redirect()->route('chat.index');
    }
}
