<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando cambia la fase del juego
 */
class GamePhaseChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $phase;
    public int $turn;
    public int $duration;
    public array $data;

    /**
     * @param int $gameId ID de la partida
     * @param string $phase Fase actual (cupido, night, wolves, witch, seer, protector, thief, day, voting)
     * @param int $turn Número de turno
     * @param int $duration Duración en segundos
     * @param array $data Datos adicionales de la fase
     */
    public function __construct(int $gameId, string $phase, int $turn, int $duration, array $data = [])
    {
        $this->gameId = $gameId;
        $this->phase = $phase;
        $this->turn = $turn;
        $this->duration = $duration;
        $this->data = $data;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'phase.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'phase' => $this->phase,
            'turn' => $this->turn,
            'duration' => $this->duration,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
