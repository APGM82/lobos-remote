<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $table = 'game_actions';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'message'
    ];
}
