<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando un jugador muere
 */
class PlayerKilled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public int $playerId;
    public string $playerNick;
    public string $cause; // 'wolves', 'village', 'witch', 'hunter', 'lovers'

    public function __construct(
        int $gameId,
        int $playerId,
        string $playerNick,
        string $cause
    ) {
        $this->gameId = $gameId;
        $this->playerId = $playerId;
        $this->playerNick = $playerNick;
        $this->cause = $cause;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'player.killed';
    }

    public function broadcastWith(): array
    {
        return [
            'playerId' => $this->playerId,
            'playerNick' => $this->playerNick,
            'cause' => $this->cause,
            'timestamp' => now()->toISOString(),
        ];
    }
}
