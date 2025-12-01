<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando un jugador emite un voto
 */
class VoteReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $votingType;
    public int $voterId;
    public int $targetId;
    public array $currentVotes;

    public function __construct(
        int $gameId,
        string $votingType,
        int $voterId,
        int $targetId,
        array $currentVotes
    ) {
        $this->gameId = $gameId;
        $this->votingType = $votingType;
        $this->voterId = $voterId;
        $this->targetId = $targetId;
        $this->currentVotes = $currentVotes;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.lobby.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.vote.received';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameId,
            'votingType' => $this->votingType,
            'voterId' => $this->voterId,
            'targetId' => $this->targetId,
            'votes' => $this->currentVotes,
            'timestamp' => now()->toISOString(),
        ];
    }
}
