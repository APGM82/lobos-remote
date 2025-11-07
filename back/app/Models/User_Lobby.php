<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User_Lobby extends Model
{
    protected $fillable = [
        'lobby_id',
        'user_id',
        'character_id',
        'is_bot',
        'status'
    ];

    public function lobbies(): HasMany {
        return $this->hasMany(Lobby::class, 'lobby_id');
    }

    public function users(): HasMany {
        return $this->hasMany(User::class, 'user_id');
    }

    public function characters(): HasMany {
        return $this->hasMany(Character::class, 'character_id');
    }
}
