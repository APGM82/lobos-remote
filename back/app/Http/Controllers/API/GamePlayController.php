<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameLobby;
use App\Events\VoteReceived;
use App\Events\VotingEnded;
use App\Events\PlayerKilled;
use App\Events\GameMessage;
use App\Events\ActionPrompt;
use App\Services\GameFlowService;
use Illuminate\Http\Request;
use Exception;

/**
 * Controlador para manejar las acciones del juego
 * La logica del flujo de fases esta en GameFlowService
 */
class GamePlayController extends Controller
{
    protected GameFlowService $gameFlow;

    public function __construct(GameFlowService $gameFlow)
    {
        $this->gameFlow = $gameFlow;
    }

    // ==================== ESTADO Y CONTROL DE FASES ====================

    /**
     * Obtener el estado actual del juego
     */
    public function getGameState(Request $request, $gameId)
    {
        try {
            $game = Game::find($gameId);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            $gameState = $this->gameFlow->getGameState($gameId);

            $players = GameLobby::where('id_game', $gameId)
                ->with(['user:id,nickname', 'character:id,name'])
                ->get()
                ->map(fn($lobby) => [
                    'id' => $lobby->id_user,
                    'lobbyId' => $lobby->id,
                    'nick' => $lobby->user->nickname ?? 'Unknown',
                    'isAlive' => (bool) $lobby->is_alive,
                    'character' => $lobby->character->name ?? null,
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'gameId' => $gameId,
                    'gameName' => $game->name,
                    'phase' => $gameState['phase'] ?? 'waiting',
                    'turn' => $gameState['turn'] ?? 0,
                    'players' => $players,
                ],
                'message' => 'Estado del juego obtenido'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Avanzar a la siguiente fase
     */
    public function nextPhase(Request $request, $gameId)
    {
        try {
            $game = Game::find($gameId);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            $flowData = $this->gameFlow->nextPhase($game);

            return response()->json([
                'success' => true,
                'data' => $flowData,
                'message' => 'Fase avanzada'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aplicar muertes nocturnas
     */
    public function applyNightDeaths(Request $request, $gameId)
    {
        try {
            $deaths = $this->gameFlow->applyNightDeaths($gameId);

            return response()->json([
                'success' => true,
                'data' => [
                    'deaths' => $deaths,
                    'count' => count($deaths)
                ],
                'message' => count($deaths) > 0
                    ? count($deaths) . ' jugador(es) murieron durante la noche'
                    : 'Nadie murio durante la noche'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== VOTACIONES ====================

    /**
     * Registrar un voto
     */
    public function vote(Request $request, $gameId)
    {
        try {
            $voterId = auth()->id() ?? $request->input('voter_id');
            $targetId = $request->input('target_id');

            if (!$voterId || !$targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parametros'
                ], 400);
            }

            // Verificar que estamos en fase de votación
            $gameState = $this->gameFlow->getGameState($gameId);
            $currentPhase = $gameState['phase'] ?? '';
            
            if (!in_array($currentPhase, ['wolves', 'day'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No es momento de votar'
                ], 400);
            }
            
            $votingType = $currentPhase === 'wolves' ? 'wolves' : 'village';

            $voter = GameLobby::where('id_game', $gameId)
                ->where('id_user', $voterId)
                ->where('is_alive', 1)
                ->with('character:id,name')
                ->first();

            if (!$voter) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes votar'
                ], 403);
            }

            $target = GameLobby::where('id_game', $gameId)
                ->where('id_user', $targetId)
                ->where('is_alive', 1)
                ->with('character:id,name')
                ->first();

            if (!$target) {
                return response()->json([
                    'success' => false,
                    'message' => 'El jugador objetivo no esta vivo'
                ], 400);
            }

            if ($votingType === 'wolves') {
                if ($voter->character->name !== GameFlowService::ROLE_WOLF) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Solo los lobos pueden votar en esta fase'
                    ], 403);
                }

                if ($target->character->name === GameFlowService::ROLE_WOLF) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No puedes votar a otro lobo'
                    ], 400);
                }
            }

            $gameState = $this->gameFlow->getGameState($gameId);
            if (isset($gameState['votes'][$voterId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya has votado'
                ], 400);
            }

            $gameState['votes'][$voterId] = $targetId;
            $this->gameFlow->saveGameState($gameId, $gameState);

            // Agrupar votos por target: target_id => [voter_ids]
            $votesByTarget = [];
            foreach ($gameState['votes'] as $voter => $target) {
                if (!isset($votesByTarget[$target])) {
                    $votesByTarget[$target] = [];
                }
                $votesByTarget[$target][] = $voter;
            }
            
            event(new VoteReceived($gameId, $votingType, $voterId, $targetId, $votesByTarget));

            return response()->json([
                'success' => true,
                'data' => ['currentVotes' => $votesByTarget],
                'message' => 'Voto registrado'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolver votacion
     */
    public function resolveVoting(Request $request, $gameId)
    {
        try {
            $votingType = $request->input('voting_type', 'village');
            $gameState = $this->gameFlow->getGameState($gameId);
            $votes = $gameState['votes'] ?? [];

            if (empty($votes)) {
                event(new VotingEnded($gameId, $votingType, null, null, []));
                event(new GameMessage($gameId, 'No hubo votos.', 'system'));
                $gameState['votes'] = [];
                $this->gameFlow->saveGameState($gameId, $gameState);

                return response()->json([
                    'success' => true,
                    'data' => ['eliminatedId' => null],
                    'message' => 'No hubo votos'
                ], 200);
            }

            $voteCounts = array_count_values($votes);
            arsort($voteCounts);

            $eliminatedId = array_key_first($voteCounts);
            $maxVotes = $voteCounts[$eliminatedId];

            $tied = array_filter($voteCounts, fn($v) => $v === $maxVotes);
            $hasTie = count($tied) > 1;

            if ($hasTie) {
                $tiedIds = array_keys($tied);
                $eliminatedId = $tiedIds[array_rand($tiedIds)];
            }

            $eliminated = GameLobby::where('id_game', $gameId)
                ->where('id_user', $eliminatedId)
                ->with(['user:id,nickname', 'character:id,name'])
                ->first();

            if (!$eliminated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jugador no encontrado'
                ], 500);
            }

            if ($votingType === 'wolves') {
                $gameState['wolfVictim'] = $eliminatedId;
                $gameState['votes'] = [];
                $this->gameFlow->saveGameState($gameId, $gameState);

                event(new VotingEnded($gameId, $votingType, $eliminatedId, $eliminated->user->nickname, $voteCounts));
                event(new GameMessage($gameId, "Los lobos han elegido a su victima...", 'system'));

                return response()->json([
                    'success' => true,
                    'data' => [
                        'eliminatedId' => $eliminatedId,
                        'eliminatedNick' => $eliminated->user->nickname,
                        'pending' => true
                    ],
                    'message' => 'Victima seleccionada'
                ], 200);
            }

            $eliminated->is_alive = 0;
            $eliminated->save();

            event(new VotingEnded($gameId, $votingType, $eliminatedId, $eliminated->user->nickname, $voteCounts));
            event(new PlayerKilled($gameId, $eliminatedId, $eliminated->user->nickname, 'village'));
            event(new GameMessage($gameId, "{$eliminated->user->nickname} ha sido linchado", 'system'));

            if ($eliminated->character && $eliminated->character->name === GameFlowService::ROLE_HUNTER) {
                $hunterTargets = $this->gameFlow->getAllAlivePlayers($gameId)
                    ->map(fn($p) => ['id' => $p->id_user, 'nick' => $p->user->nickname ?? 'Unknown'])
                    ->toArray();
                event(new ActionPrompt($gameId, $eliminatedId, 'hunter', 30, $hunterTargets, false));
            }

            $this->gameFlow->checkWinCondition($gameId);
            $gameState['votes'] = [];
            $this->gameFlow->saveGameState($gameId, $gameState);

            return response()->json([
                'success' => true,
                'data' => [
                    'eliminatedId' => $eliminatedId,
                    'eliminatedNick' => $eliminated->user->nickname,
                    'eliminatedRole' => $eliminated->character->name ?? null
                ],
                'message' => 'Votacion resuelta'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== ACCIONES DE PERSONAJES ====================

    public function cupidAction(Request $request, $gameId)
    {
        try {
            $player1Id = $request->input('player1_id');
            $player2Id = $request->input('player2_id');

            if (!$player1Id || !$player2Id || $player1Id === $player2Id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes seleccionar 2 jugadores diferentes'
                ], 400);
            }

            $gameState = $this->gameFlow->getGameState($gameId);
            $gameState['lovers'] = [$player1Id, $player2Id];
            $this->gameFlow->saveGameState($gameId, $gameState);

            event(new GameMessage($gameId, "Cupido ha unido a dos corazones...", 'system'));

            return response()->json([
                'success' => true,
                'message' => 'Enamorados elegidos'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function thiefAction(Request $request, $gameId)
    {
        try {
            $targetId = $request->input('target_id');
            $skip = $request->input('skip', false);

            $gameState = $this->gameFlow->getGameState($gameId);

            if ($gameState['thiefUsed']) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ladron ya uso su habilidad'
                ], 400);
            }

            $gameState['thiefUsed'] = true;
            $this->gameFlow->saveGameState($gameId, $gameState);

            if (!$skip) {
                event(new GameMessage($gameId, "El ladron ha actuado...", 'system'));
            }

            return response()->json([
                'success' => true,
                'message' => $skip ? 'El ladron no robo' : 'Rol intercambiado'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function protectorAction(Request $request, $gameId)
    {
        try {
            $targetId = $request->input('target_id');

            if (!$targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes seleccionar un jugador'
                ], 400);
            }

            $gameState = $this->gameFlow->getGameState($gameId);

            if ($gameState['lastProtected'] === $targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes proteger al mismo jugador dos noches seguidas'
                ], 400);
            }

            $gameState['protectedPlayer'] = $targetId;
            $gameState['lastProtected'] = $targetId;
            $this->gameFlow->saveGameState($gameId, $gameState);

            event(new GameMessage($gameId, "El protector ha elegido...", 'system'));

            return response()->json([
                'success' => true,
                'message' => 'Jugador protegido'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function seerAction(Request $request, $gameId)
    {
        try {
            $targetId = $request->input('target_id');

            if (!$targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Debes seleccionar un jugador'
                ], 400);
            }

            $target = GameLobby::where('id_game', $gameId)
                ->where('id_user', $targetId)
                ->with(['user:id,nickname', 'character:id,name'])
                ->first();

            if (!$target) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jugador no encontrado'
                ], 404);
            }

            $isWolf = $target->character->name === GameFlowService::ROLE_WOLF;

            event(new GameMessage($gameId, "La vidente ha tenido una vision...", 'system'));

            return response()->json([
                'success' => true,
                'data' => [
                    'targetId' => $targetId,
                    'targetNick' => $target->user->nickname,
                    'isWolf' => $isWolf,
                    'character' => $target->character->name
                ],
                'message' => 'Vision completada'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function witchAction(Request $request, $gameId)
    {
        try {
            $action = $request->input('action');
            $targetId = $request->input('target_id');

            $gameState = $this->gameFlow->getGameState($gameId);

            if ($action === 'save') {
                if ($gameState['witchLifeUsed']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya usaste la pocion de vida'
                    ], 400);
                }

                $gameState['witchSavedVictim'] = true;
                $gameState['witchLifeUsed'] = true;
                $this->gameFlow->saveGameState($gameId, $gameState);

                event(new GameMessage($gameId, "La bruja uso pocion de vida...", 'system'));

                return response()->json([
                    'success' => true,
                    'message' => 'Victima salvada'
                ], 200);
            }

            if ($action === 'kill') {
                if ($gameState['witchDeathUsed']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya usaste la pocion de muerte'
                    ], 400);
                }

                if (!$targetId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Debes seleccionar un objetivo'
                    ], 400);
                }

                $gameState['witchKill'] = $targetId;
                $gameState['witchDeathUsed'] = true;
                $this->gameFlow->saveGameState($gameId, $gameState);

                event(new GameMessage($gameId, "La bruja uso pocion de muerte...", 'system'));

                return response()->json([
                    'success' => true,
                    'message' => 'Pocion de muerte usada'
                ], 200);
            }

            return response()->json([
                'success' => true,
                'message' => 'La bruja no actuo'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function hunterAction(Request $request, $gameId)
    {
        try {
            $targetId = $request->input('target_id');

            if (!$targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cazador debe disparar'
                ], 400);
            }

            $target = GameLobby::where('id_game', $gameId)
                ->where('id_user', $targetId)
                ->where('is_alive', 1)
                ->with(['user:id,nickname', 'character:id,name'])
                ->first();

            if (!$target) {
                return response()->json([
                    'success' => false,
                    'message' => 'Objetivo invalido'
                ], 400);
            }

            $target->is_alive = 0;
            $target->save();

            event(new PlayerKilled($gameId, $targetId, $target->user->nickname, 'hunter'));
            event(new GameMessage($gameId, "El cazador disparo a {$target->user->nickname}!", 'system'));

            $this->gameFlow->checkWinCondition($gameId);

            return response()->json([
                'success' => true,
                'data' => [
                    'targetId' => $targetId,
                    'targetNick' => $target->user->nickname
                ],
                'message' => 'Disparo realizado'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
