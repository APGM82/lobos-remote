<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameLobby extends Model
{
    public $table = 'game_lobbies';

    public $timestamps = false;

    protected $fillable = [
        'id_game',
        'id_user',
        'id_character',
        'is_alive'
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'id_character', 'id');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'id_game', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id');
    }
}
