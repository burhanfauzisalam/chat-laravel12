<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'topic',
        'invite_code',
    ];

    protected static function booted(): void
    {
        static::creating(function (Room $room): void {
            if (empty($room->invite_code)) {
                $room->invite_code = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'invite_code';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('last_read_at');
    }
}


