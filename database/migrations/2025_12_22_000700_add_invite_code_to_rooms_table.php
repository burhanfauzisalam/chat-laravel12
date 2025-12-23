<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        // Tambah kolom dulu tanpa unique index
        Schema::table('rooms', function (Blueprint $table) {
            $table->string('invite_code')->nullable()->after('topic');
        });

        // Generate unique invite codes untuk data yang sudah ada
        $rooms = DB::table('rooms')->select('id')->get();

        foreach ($rooms as $room) {
            DB::table('rooms')
                ->where('id', $room->id)
                ->update(['invite_code' => (string) Str::uuid()]);
        }

        // Baru setelah semua terisi, tambahkan unique index
        Schema::table('rooms', function (Blueprint $table) {
            $table->unique('invite_code');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropUnique('rooms_invite_code_unique');
            $table->dropColumn('invite_code');
        });
    }
};
