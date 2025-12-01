<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ChatMessage extends Model
{
    // Constantes para tipos de mensaje
    public const TYPE_PUBLIC = 'public';
    public const TYPE_PRIVATE = 'private';
    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'game_id',
        'user_id',
        'message',
        'type',
        'recipient_id',
        'recipient_ids'
    ];

    protected $casts = [
        'recipient_ids' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con el usuario que envió el mensaje
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relación con el destinatario (para mensajes privados)
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    /**
     * Relación con la partida
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    /**
     * Scope para obtener mensajes archivados de una partida
     */
    public function scopeArchiveMessagesForGame(Builder $query, int $gameId, int $limit = 50): Builder
    {
        return $query->where('game_id', $gameId)
            ->with(['user:id,name,nickname,image', 'recipient:id,name,nickname,image'])
            ->orderBy('created_at', 'asc')
            ->limit($limit);
    }

    /**
     * Scope para mensajes privados donde el usuario es remitente o destinatario
     */
    public function scopePrivateForUser(Builder $query, int $userId, int $gameId): Builder
    {
        return $query->where('game_id', $gameId)
            ->where('type', self::TYPE_PRIVATE)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                  ->orWhere('recipient_id', $userId);
            })
            ->with(['user:id,name,nickname,image', 'recipient:id,name,nickname,image']);
    }

    /**
     * Scope para mensajes de grupo que incluyen al usuario
     */
    public function scopeGroupForUser(Builder $query, int $userId, int $gameId): Builder
    {
        return $query->where('game_id', $gameId)
            ->where('type', self::TYPE_GROUP)
            ->whereJsonContains('recipient_ids', $userId)
            ->with(['user:id,name,nickname,image']);
    }
}
