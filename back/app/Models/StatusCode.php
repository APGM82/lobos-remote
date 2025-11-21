<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusCode extends Model
{
    protected $table = 'game_status';
    protected $fillable = [
        'code_status',
        'name'
    ];
}
