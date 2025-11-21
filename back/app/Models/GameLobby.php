<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameLobby extends Model
{
    public $table = 'game_lobbies';

    protected $fillable = [
        'id_game',
        'id_user',
        'id_character'
    ];

    public function character() : HasMany {
        return $this->hasMany(Character::class, 'id_game', 'id_character');
    }

    public function game() : HasMany {
        return $this->hasMany(Game::class, 'id_game', 'id_game');
    }

    public function user() : HasMany {
        return $this->hasMany(User::class, 'id_game', 'id_user');
    }
}
