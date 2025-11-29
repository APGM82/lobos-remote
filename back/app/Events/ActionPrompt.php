<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para pedir una acción a un jugador específico
 * Solo el jugador indicado debe responder
 */
class ActionPrompt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public int $targetPlayerId; // Jugador que debe actuar
    public string $actionType; // 'cupido', 'thief', 'protector', 'seer', 'witch', etc.
    public int $duration; // Tiempo para decidir
    public array $options; // Opciones disponibles (jugadores a elegir, etc)
    public bool $canSkip; // Si puede saltar la acción

    public function __construct(
        int $gameId,
        int $targetPlayerId,
        string $actionType,
        int $duration,
        array $options = [],
        bool $canSkip = true
    ) {
        $this->gameId = $gameId;
        $this->targetPlayerId = $targetPlayerId;
        $this->actionType = $actionType;
        $this->duration = $duration;
        $this->options = $options;
        $this->canSkip = $canSkip;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'action.prompt';
    }

    public function broadcastWith(): array
    {
        return [
            'targetPlayerId' => $this->targetPlayerId,
            'actionType' => $this->actionType,
            'duration' => $this->duration,
            'options' => $this->options,
            'canSkip' => $this->canSkip,
            'timestamp' => now()->toISOString(),
        ];
    }
}
