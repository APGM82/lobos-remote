<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lobby extends Model
{
    protected $table = 'lobbies';
    protected $fillable = [
        'code',
        'status'
    ];
}
