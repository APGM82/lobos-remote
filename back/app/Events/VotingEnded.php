<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se dispara cuando termina una votación
 */
class VotingEnded implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $gameId;
    public string $votingType;
    public ?int $eliminatedPlayerId;
    public ?string $eliminatedPlayerNick;
    public array $finalVotes;

    public function __construct(
        int $gameId,
        string $votingType,
        ?int $eliminatedPlayerId,
        ?string $eliminatedPlayerNick,
        array $finalVotes
    ) {
        $this->gameId = $gameId;
        $this->votingType = $votingType;
        $this->eliminatedPlayerId = $eliminatedPlayerId;
        $this->eliminatedPlayerNick = $eliminatedPlayerNick;
        $this->finalVotes = $finalVotes;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('game.' . $this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'voting.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'votingType' => $this->votingType,
            'eliminatedPlayerId' => $this->eliminatedPlayerId,
            'eliminatedPlayerNick' => $this->eliminatedPlayerNick,
            'finalVotes' => $this->finalVotes,
            'timestamp' => now()->toISOString(),
        ];
    }
}
