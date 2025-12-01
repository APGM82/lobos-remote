<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatMessage;

    public function __construct(ChatMessage $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    /**
     * Determinar en qué canal(es) transmitir el mensaje según su tipo
     */
    public function broadcastOn()
    {
        $gameId = $this->chatMessage->game_id;
        
        switch ($this->chatMessage->type) {
            case ChatMessage::TYPE_PUBLIC:
                // Mensaje público: todos los jugadores de la partida
                return new Channel("chat.game.{$gameId}.public");
                
            case ChatMessage::TYPE_PRIVATE:
                // Mensaje privado: solo remitente y destinatario
                return [
                    new PrivateChannel("chat.game.{$gameId}.private.{$this->chatMessage->user_id}"),
                    new PrivateChannel("chat.game.{$gameId}.private.{$this->chatMessage->recipient_id}"),
                ];
                
            case ChatMessage::TYPE_GROUP:
                // Mensaje de grupo: cada destinatario tiene su propio canal privado
                $channels = [];
                foreach ($this->chatMessage->recipient_ids as $recipientId) {
                    $channels[] = new PrivateChannel("chat.game.{$gameId}.group.{$recipientId}");
                }
                // También incluir al remitente
                $channels[] = new PrivateChannel("chat.game.{$gameId}.group.{$this->chatMessage->user_id}");
                return $channels;
                
            default:
                return new Channel("chat.game.{$gameId}.public");
        }
    }

    /**
     * Nombre del evento en el cliente
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Datos que se envían al cliente
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->chatMessage->id,
            'game_id' => $this->chatMessage->game_id,
            'type' => $this->chatMessage->type,
            'message' => $this->chatMessage->message,
            'user_id' => $this->chatMessage->user_id,
            'user_name' => $this->chatMessage->user->name ?? null,
            'user_nickname' => $this->chatMessage->user->nickname ?? null,
            'user_image' => $this->chatMessage->user->image ?? null,
            'recipient_id' => $this->chatMessage->recipient_id,
            'recipient_ids' => $this->chatMessage->recipient_ids,
            'created_at' => $this->chatMessage->created_at->toISOString(),
        ];
    }
}