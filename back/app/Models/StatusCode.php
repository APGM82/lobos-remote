<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusCode extends Model
{
    protected $table = 'status_codes';
    protected $fillable = [
        'code_status',
        'name'
    ];
}
