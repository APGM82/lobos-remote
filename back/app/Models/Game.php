<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Game extends Model
{

    protected $table = 'games';

    protected $fillable = [
        'id_user_host',
        'code_join_to',
        'code_status',
        'name',
        'max_players'
    ];

    public function userHost() {
        return $this->belongsTo(User::class, 'id_user_host');
    }

    public function status() {
        return $this->belongsTo(StatusCode::class, 'code_status');
    }

    public function users() {
        return $this->belongsToMany(User::class, 'game_lobbies', 'id_game', 'id_user');
    }
}
