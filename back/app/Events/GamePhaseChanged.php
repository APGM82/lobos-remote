<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamePhaseChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $game;
    public $phase;
    public $phaseMessage;
    public $duration;
    public $votingInProgress;

    public function __construct(Game $game, string $phase, string $phaseMessage = '', int $duration = 0, bool $votingInProgress = false)
    {
        $this->game = $game;
        $this->phase = $phase;
        $this->phaseMessage = $phaseMessage;
        $this->duration = $duration;
        $this->votingInProgress = $votingInProgress;
    }

    public function broadcastOn()
    {
        return new Channel("game.lobby.{$this->game->id}");
    }

    public function broadcastAs(): string
    {
        return 'game.phase.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->game->id,
            'phase' => $this->phase,
            'phase_message' => $this->phaseMessage,
            'duration' => $this->duration,
            'voting_in_progress' => $this->votingInProgress,
        ];
    }
}
