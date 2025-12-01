<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\GameLobby;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

/**
 * Canal público para mensajes públicos de una partida
 */
Broadcast::channel('chat.game.{gameId}.public', function ($user, $gameId) {
    // Verificar que el usuario esté en la partida
    return GameLobby::where('id_game', $gameId)
        ->where('id_user', $user->id)
        ->exists();
});

/**
 * Canal privado para mensajes privados
 * Solo el remitente y el destinatario pueden acceder
 */
Broadcast::channel('chat.game.{gameId}.private.{userId}', function ($user, $gameId, $targetUserId) {
    // Verificar que el usuario esté en la partida
    $inGame = GameLobby::where('id_game', $gameId)
        ->where('id_user', $user->id)
        ->exists();
    
    if (!$inGame) {
        return false;
    }
    
    // Solo permitir si el usuario es el remitente o el destinatario
    return (int)$user->id === (int)$targetUserId;
});

/**
 * Canal privado para mensajes de grupo
 * Solo los usuarios que están en el grupo pueden acceder
 */
Broadcast::channel('chat.game.{gameId}.group.{userId}', function ($user, $gameId, $targetUserId) {
    // Verificar que el usuario esté en la partida
    $inGame = GameLobby::where('id_game', $gameId)
        ->where('id_user', $user->id)
        ->exists();
    
    if (!$inGame) {
        return false;
    }
    
    // Solo permitir si el usuario es el destinatario del canal
    return (int)$user->id === (int)$targetUserId;
});

/**
 * Canal público para actualizaciones del lobby de una partida
 * Solo los usuarios que están en la partida pueden acceder
 */
Broadcast::channel('game.lobby.{gameId}', function ($user, $gameId) {
    // Verificar que el usuario esté en la partida
    return GameLobby::where('id_game', $gameId)
        ->where('id_user', $user->id)
        ->exists();
});

