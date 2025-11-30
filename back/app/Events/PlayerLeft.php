<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerLeft implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $game;
    public $player;
    public $gameDeleted;

    public function __construct(Game $game, $player, bool $gameDeleted = false)
    {
        $this->game = $game;
        $this->player = $player;
        $this->gameDeleted = $gameDeleted;
    }

    /**
     * Canal donde se transmite el evento
     */
    public function broadcastOn()
    {
        return new Channel("game.lobby.{$this->game->id}");
    }

    /**
     * Nombre del evento en el cliente
     */
    public function broadcastAs(): string
    {
        return 'player.left';
    }

    /**
     * Datos que se envían al cliente
     */
    public function broadcastWith(): array
    {
        // Si la partida fue eliminada, no cargar relaciones
        if ($this->gameDeleted) {
            return [
                'game_id' => $this->game->id,
                'player' => [
                    'id' => $this->player['id'] ?? null,
                    'name' => $this->player['name'] ?? null,
                    'nickname' => $this->player['nickname'] ?? null,
                ],
                'game_deleted' => true,
                'message' => 'La partida ha sido eliminada por no tener jugadores',
            ];
        }

        // Recargar la partida con todas las relaciones actualizadas
        $this->game->load(['userHost:id,name,nickname,email', 'status:id,code_status,name', 'users:id,name,nickname']);
        
        return [
            'game_id' => $this->game->id,
            'player' => [
                'id' => $this->player['id'] ?? null,
                'name' => $this->player['name'] ?? null,
                'nickname' => $this->player['nickname'] ?? null,
            ],
            'game_deleted' => false,
            'game' => [
                'id' => $this->game->id,
                'name' => $this->game->name,
                'max_players' => $this->game->max_players,
                'current_players' => $this->game->users->count(),
                'status' => [
                    'id' => $this->game->status->id ?? null,
                    'code' => $this->game->status->code_status ?? null,
                    'name' => $this->game->status->name ?? 'unknown',
                ],
                'code_join_to' => $this->game->code_join_to,
                'host' => [
                    'id' => $this->game->userHost->id ?? null,
                    'name' => $this->game->userHost->name ?? null,
                    'nickname' => $this->game->userHost->nickname ?? null,
                ],
                'players' => $this->game->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'nickname' => $user->nickname,
                    ];
                }),
            ],
        ];
    }
}

