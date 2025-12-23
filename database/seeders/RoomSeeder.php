<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['name' => 'General', 'topic' => 'chat/general'],
            ['name' => 'Support', 'topic' => 'chat/support'],
            ['name' => 'Random', 'topic' => 'chat/random'],
        ];

        $admin = User::where('username', 'admin')->first();

        foreach ($defaults as $room) {
            $roomModel = Room::updateOrCreate(['topic' => $room['topic']], $room);

            if ($admin) {
                $roomModel->users()->syncWithoutDetaching([
                    $admin->id => ['last_read_at' => now()],
                ]);
            }
        }
    }
}


