<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para enviar mensajes del sistema o chat
 */
class GameMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $message;
    public string $type; // 'system', 'chat', 'wolves_chat', 'action'
    public ?int $fromPlayerId;
    public ?string $fromPlayerNick;

    public function __construct(
        int $gameId,
        string $message,
        string $type = 'system',
        ?int $fromPlayerId = null,
        ?string $fromPlayerNick = null
    ) {
        $this->gameId = $gameId;
        $this->message = $message;
        $this->type = $type;
        $this->fromPlayerId = $fromPlayerId;
        $this->fromPlayerNick = $fromPlayerNick;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.message';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
            'type' => $this->type,
            'fromPlayerId' => $this->fromPlayerId,
            'fromPlayerNick' => $this->fromPlayerNick,
            'timestamp' => now()->toISOString(),
        ];
    }
}
