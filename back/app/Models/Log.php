<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    protected $table = 'game_logs';

    protected $fillable = [
        'user_lobby_id',
        'turn',
        'stage',
        'action_id',
        'user_id',
        'affected_user_id',
    ];

    public function user_lobby(): BelongsTo {
        return $this->belongsTo(User::class, 'user_lobby_id');
    }

    public function actions(): BelongsTo {
        return $this->belongsTo(Action::class, 'action_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function affected_user(): BelongsTo {
        return $this->belongsTo(User::class, 'affected_user_id');
    }
}
