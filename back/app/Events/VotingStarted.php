<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando comienza una votación
 */
class VotingStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $votingType; // 'wolves' o 'village'
    public int $duration;
    public array $eligibleVoters; // IDs de jugadores que pueden votar
    public array $eligibleTargets; // IDs de jugadores que pueden ser votados

    public function __construct(
        int $gameId,
        string $votingType,
        int $duration,
        array $eligibleVoters,
        array $eligibleTargets
    ) {
        $this->gameId = $gameId;
        $this->votingType = $votingType;
        $this->duration = $duration;
        $this->eligibleVoters = $eligibleVoters;
        $this->eligibleTargets = $eligibleTargets;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'voting.started';
    }

    public function broadcastWith(): array
    {
        return [
            'votingType' => $this->votingType,
            'duration' => $this->duration,
            'eligibleVoters' => $this->eligibleVoters,
            'eligibleTargets' => $this->eligibleTargets,
            'timestamp' => now()->toISOString(),
        ];
    }
}
