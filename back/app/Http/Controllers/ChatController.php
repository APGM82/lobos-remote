<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Verificar que el usuario esté en la partida
     */
    private function verifyUserInGame(int $userId, int $gameId): bool
    {
        return GameLobby::where('id_game', $gameId)
            ->where('id_user', $userId)
            ->exists();
    }

    /**
     * Verificar que el destinatario NO sea "Aldeano"
     */
    private function verifyRecipientNotAldeano(int $recipientId, int $gameId): bool
    {
        $lobby = GameLobby::where('id_game', $gameId)
            ->where('id_user', $recipientId)
            ->with('character')
            ->first();

        if (!$lobby) {
            return false;
        }

        // Obtener el personaje del destinatario
        $character = Character::find($lobby->id_character);
        
        // Verificar que NO sea "Aldeano"
        return $character && strtolower($character->name) !== 'aldeano';
    }

    /**
     * Enviar mensaje público a todos los jugadores de la partida
     */
    public function sendPublic(Request $request, $gameId)
    {
        $user = $request->user();
        $gameId = (int) $gameId;

        // Validar que el usuario esté en la partida
        if (!$this->verifyUserInGame($user->id, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'No estás en esta partida'
            ], 403);
        }

        // Validar el mensaje
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Crear mensaje público
        $chatMessage = ChatMessage::create([
            'game_id' => $gameId,
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'type' => ChatMessage::TYPE_PUBLIC,
        ]);

        // Cargar relaciones para el evento
        $chatMessage->load(['user:id,name,nickname,image']);

        // Disparar evento
        event(new MessageSent($chatMessage));

        return response()->json([
            'success' => true,
            'message' => 'Mensaje público enviado correctamente',
            'data' => $chatMessage
        ]);
    }

    /**
     * Enviar mensaje privado a un jugador específico
     */
    public function sendPrivate(Request $request, $gameId)
    {
        $user = $request->user();
        $gameId = (int) $gameId;

        // Validar que el usuario esté en la partida
        if (!$this->verifyUserInGame($user->id, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'No estás en esta partida'
            ], 403);
        }

        // Validar el request
        $request->validate([
            'recipient_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $recipientId = (int) $request->input('recipient_id');

        // Verificar que el destinatario esté en la partida
        if (!$this->verifyUserInGame($recipientId, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'El destinatario no está en esta partida'
            ], 422);
        }

        // Validar que el destinatario NO sea "Aldeano"
        if (!$this->verifyRecipientNotAldeano($recipientId, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'No se pueden enviar mensajes privados a jugadores con personaje "Aldeano"'
            ], 422);
        }

        // Crear mensaje privado
        $chatMessage = ChatMessage::create([
            'game_id' => $gameId,
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'type' => ChatMessage::TYPE_PRIVATE,
            'recipient_id' => $recipientId,
        ]);

        // Cargar relaciones para el evento
        $chatMessage->load(['user:id,name,nickname,image', 'recipient:id,name,nickname,image']);

        // Disparar evento
        event(new MessageSent($chatMessage));

        return response()->json([
            'success' => true,
            'message' => 'Mensaje privado enviado correctamente',
            'data' => $chatMessage
        ]);
    }

    /**
     * Enviar mensaje a un grupo de jugadores
     */
    public function sendGroup(Request $request, $gameId)
    {
        $user = $request->user();
        $gameId = (int) $gameId;

        // Validar que el usuario esté en la partida
        if (!$this->verifyUserInGame($user->id, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'No estás en esta partida'
            ], 403);
        }

        // Validar el request
        $request->validate([
            'recipient_ids' => 'required|array|min:2',
            'recipient_ids.*' => 'integer|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $recipientIds = array_map('intval', $request->input('recipient_ids'));

        // Verificar que todos los destinatarios estén en la partida
        foreach ($recipientIds as $recipientId) {
            if (!$this->verifyUserInGame($recipientId, $gameId)) {
                return response()->json([
                    'success' => false,
                    'message' => "El usuario con ID {$recipientId} no está en esta partida"
                ], 422);
            }
        }

        // Crear mensaje de grupo
        $chatMessage = ChatMessage::create([
            'game_id' => $gameId,
            'user_id' => $user->id,
            'message' => $request->input('message'),
            'type' => ChatMessage::TYPE_GROUP,
            'recipient_ids' => $recipientIds,
        ]);

        // Cargar relaciones para el evento
        $chatMessage->load(['user:id,name,nickname,image']);

        // Disparar evento
        event(new MessageSent($chatMessage));

        return response()->json([
            'success' => true,
            'message' => 'Mensaje de grupo enviado correctamente',
            'data' => $chatMessage
        ]);
    }

    /**
     * Obtener historial de mensajes de la partida
     */
    public function getHistory($gameId)
    {
        $user = request()->user();
        $gameId = (int) $gameId;

        // Validar que el usuario esté en la partida
        if (!$this->verifyUserInGame($user->id, $gameId)) {
            return response()->json([
                'success' => false,
                'message' => 'No estás en esta partida'
            ], 403);
        }

        // Obtener mensajes públicos
        $publicMessages = ChatMessage::where('game_id', $gameId)
            ->where('type', ChatMessage::TYPE_PUBLIC)
            ->with(['user:id,name,nickname,image'])
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Obtener mensajes privados donde el usuario es remitente o destinatario
        $privateMessages = ChatMessage::privateForUser($user->id, $gameId)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Obtener mensajes de grupo que incluyen al usuario
        $groupMessages = ChatMessage::groupForUser($user->id, $gameId)
            ->orderBy('created_at', 'asc')
            ->limit(50)
            ->get();

        // Combinar y ordenar todos los mensajes
        $allMessages = $publicMessages
            ->concat($privateMessages)
            ->concat($groupMessages)
            ->sortBy('created_at')
            ->take(50)
            ->values();

        // Formatear mensajes para la respuesta
        $formattedMessages = $allMessages->map(function ($msg) {
            return [
                'id' => $msg->id,
                'type' => $msg->type,
                'message' => $msg->message,
                'user_id' => $msg->user_id,
                'user_name' => $msg->user->name ?? null,
                'user_nickname' => $msg->user->nickname ?? null,
                'user_image' => $msg->user->image ?? null,
                'recipient_id' => $msg->recipient_id,
                'recipient_ids' => $msg->recipient_ids,
                'created_at' => $msg->created_at->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedMessages
        ]);
    }
}
