<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusCode extends Model
{
    use HasFactory;

    protected $table = 'game_status';
    protected $fillable = [
        'code_status',
        'name'
    ];
}
