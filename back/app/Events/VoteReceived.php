<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VoteReceived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $game;
    public $voterId;
    public $targetId;
    public $votes;

    public function __construct(Game $game, int $voterId, int $targetId, array $votes)
    {
        $this->game = $game;
        $this->voterId = $voterId;
        $this->targetId = $targetId;
        $this->votes = $votes;
    }

    public function broadcastOn()
    {
        return new Channel("game.lobby.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'game.vote.received';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'voter_id' => $this->voterId,
            'target_id' => $this->targetId,
            'votes' => $this->votes,
        ];
    }
}
