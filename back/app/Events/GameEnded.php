<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando termina la partida
 */
class GameEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $winner; // 'wolves', 'village', 'lovers'
    public array $survivors;

    public function __construct(
        int $gameId,
        string $winner,
        array $survivors
    ) {
        $this->gameId = $gameId;
        $this->winner = $winner;
        $this->survivors = $survivors;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'winner' => $this->winner,
            'survivors' => $this->survivors,
            'timestamp' => now()->toISOString(),
        ];
    }
}
