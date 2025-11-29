<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\Character;
use App\Events\GamePhaseChanged;
use App\Events\VotingStarted;
use App\Events\VoteReceived;
use App\Events\VotingEnded;
use App\Events\PlayerKilled;
use App\Events\GameMessage;
use App\Events\ActionPrompt;
use App\Events\GameEnded;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Controlador para manejar la lógica del juego en tiempo real
 * 
 * FASES DEL JUEGO:
 * - Fase 0: Cupido (20s) - Solo primera noche
 * - Fase 1: Ladrón (5s pregunta + 10s acción)
 * - Fase 2: Protector (5s pregunta + 10s acción)
 * - Fase 3: Vidente (5s pregunta + 10s acción) + Lobos (chat)
 * - Fase 4: Bruja (5s pregunta + 5s elección + 10s acción)
 * - Fase 5: Día - Aldeanos (votación 2min)
 */
class GamePlayController extends Controller
{
    // Constantes de tiempos (en segundos)
    const PHASE_CUPIDO = 20;
    const PHASE_THIEF_ASK = 5;
    const PHASE_THIEF_ACTION = 10;
    const PHASE_PROTECTOR_ASK = 5;
    const PHASE_PROTECTOR_ACTION = 10;
    const PHASE_SEER_ASK = 5;
    const PHASE_SEER_ACTION = 10;
    const PHASE_WOLVES = 60; // Chat de lobos
    const PHASE_WITCH_ASK = 5;
    const PHASE_WITCH_CHOOSE = 5;
    const PHASE_WITCH_ACTION = 10;
    const PHASE_VILLAGE = 120; // 2 minutos

    // Nombres de personajes (deben coincidir con la BD)
    const ROLE_WOLF = 'Hombre Lobo';
    const ROLE_VILLAGER = 'Aldeano';
    const ROLE_SEER = 'Vidente';
    const ROLE_WITCH = 'Bruja';
    const ROLE_HUNTER = 'Cazador';
    const ROLE_CUPID = 'Cupido';
    const ROLE_THIEF = 'Ladrón';
    const ROLE_PROTECTOR = 'Protector';
    const ROLE_GIRL = 'Niña';

    /**
     * Obtener el estado actual del juego
     */
    public function getGameState(Request $request, $gameId)
    {
        try {
            $game = Game::with(['users'])->find($gameId);
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Obtener estado del cache
            $gameState = $this->getGameStateFromCache($gameId);

            // Obtener jugadores con sus roles y estado
            $players = GameLobby::where('id_game', $gameId)
                ->with(['user:id,nickname', 'character:id,name'])
                ->get()
                ->map(function ($lobby) {
                    return [
                        'id' => $lobby->id_user,
                        'lobbyId' => $lobby->id,
                        'nick' => $lobby->user->nickname ?? 'Unknown',
                        'isAlive' => (bool) $lobby->is_alive,
                        'character' => $lobby->character->name ?? null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'gameId' => $gameId,
                    'gameName' => $game->name,
                    'phase' => $gameState['phase'] ?? 'waiting',
                    'turn' => $gameState['turn'] ?? 0,
                    'players' => $players,
                    'timeRemaining' => $gameState['timeRemaining'] ?? 0,
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
     * Iniciar el juego
     */
    public function startGame(Request $request, $gameId)
    {
        try {
            $game = Game::find($gameId);
            
            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Inicializar estado del juego en cache
            $gameState = [
                'phase' => 'cupido',
                'turn' => 1,
                'votes' => [],
                'wolfVictim' => null,
                'protectedPlayer' => null,
                'lastProtected' => null,
                'lovers' => [],
                'thiefUsed' => false,
                'witchLifeUsed' => false,
                'witchDeathUsed' => false,
            ];
            
            Cache::put("game_state_{$gameId}", $gameState, 3600); // 1 hora

            // Emitir evento de cambio de fase
            event(new GamePhaseChanged($gameId, 'cupido', 1, self::PHASE_CUPIDO, [
                'message' => 'Cupido despierta y elige a los enamorados...'
            ]));

            event(new GameMessage($gameId, '🌙 La noche cae sobre el pueblo...', 'system'));
            event(new GameMessage($gameId, '💘 Cupido despierta y debe elegir a los enamorados', 'system'));

            return response()->json([
                'success' => true,
                'message' => 'Juego iniciado'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Avanzar a la siguiente fase del juego
     */
    public function nextPhase(Request $request, $gameId)
    {
        try {
            $gameState = $this->getGameStateFromCache($gameId);
            $currentPhase = $gameState['phase'];
            $turn = $gameState['turn'];

            // Determinar siguiente fase
            $nextPhase = $this->getNextPhase($currentPhase, $turn);
            
            // Si es nueva noche, incrementar turno
            if ($currentPhase === 'day' && $nextPhase === 'thief') {
                $turn++;
            }

            // Actualizar estado
            $gameState['phase'] = $nextPhase;
            $gameState['turn'] = $turn;
            Cache::put("game_state_{$gameId}", $gameState, 3600);

            // Obtener duración y datos de la fase
            $phaseData = $this->getPhaseData($nextPhase, $gameId, $gameState);

            // Emitir evento de cambio de fase
            event(new GamePhaseChanged(
                $gameId, 
                $nextPhase, 
                $turn, 
                $phaseData['duration'],
                $phaseData['data']
            ));

            return response()->json([
                'success' => true,
                'data' => [
                    'phase' => $nextPhase,
                    'turn' => $turn,
                    'duration' => $phaseData['duration']
                ],
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
     * Iniciar votación de lobos
     */
    public function startWolvesVoting(Request $request, $gameId)
    {
        try {
            $gameState = $this->getGameStateFromCache($gameId);
            $gameState['votes'] = [];
            Cache::put("game_state_{$gameId}", $gameState, 3600);

            // Obtener lobos vivos
            $wolves = $this->getAlivePlayers($gameId, self::ROLE_WOLF);
            $wolfIds = $wolves->pluck('id_user')->toArray();

            // Obtener posibles víctimas (todos excepto lobos)
            $targets = $this->getAlivePlayersExcept($gameId, $wolfIds);
            $targetIds = $targets->pluck('id_user')->toArray();

            event(new VotingStarted(
                $gameId,
                'wolves',
                self::PHASE_WOLVES,
                $wolfIds,
                $targetIds
            ));

            event(new GameMessage($gameId, '🐺 Los lobos despiertan y eligen a su víctima...', 'system'));

            return response()->json([
                'success' => true,
                'data' => [
                    'wolves' => $wolfIds,
                    'targets' => $targetIds,
                    'duration' => self::PHASE_WOLVES
                ],
                'message' => 'Votación de lobos iniciada'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar votación de aldeanos (día)
     */
    public function startVillageVoting(Request $request, $gameId)
    {
        try {
            $gameState = $this->getGameStateFromCache($gameId);
            $gameState['votes'] = [];
            Cache::put("game_state_{$gameId}", $gameState, 3600);

            // Obtener todos los jugadores vivos
            $alivePlayers = $this->getAllAlivePlayers($gameId);
            $playerIds = $alivePlayers->pluck('id_user')->toArray();

            event(new VotingStarted(
                $gameId,
                'village',
                self::PHASE_VILLAGE,
                $playerIds,
                $playerIds
            ));

            event(new GameMessage($gameId, '☀️ Amanece en el pueblo...', 'system'));
            event(new GameMessage($gameId, '🗳️ Los aldeanos deben votar a quién linchar', 'system'));

            return response()->json([
                'success' => true,
                'data' => [
                    'voters' => $playerIds,
                    'targets' => $playerIds,
                    'duration' => self::PHASE_VILLAGE
                ],
                'message' => 'Votación de aldeanos iniciada'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Registrar un voto
     */
    public function vote(Request $request, $gameId)
    {
        try {
            $voterId = $request->input('voter_id');
            $targetId = $request->input('target_id');
            $votingType = $request->input('voting_type', 'village');

            if (!$voterId || !$targetId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Faltan parámetros'
                ], 400);
            }

            // Verificar que el votante está vivo
            $voter = GameLobby::where('id_game', $gameId)
                ->where('id_user', $voterId)
                ->where('is_alive', 1)
                ->first();

            if (!$voter) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes votar'
                ], 403);
            }

            // Registrar voto
            $gameState = $this->getGameStateFromCache($gameId);
            $gameState['votes'][$voterId] = $targetId;
            Cache::put("game_state_{$gameId}", $gameState, 3600);

            // Calcular votos actuales
            $voteCounts = array_count_values($gameState['votes']);

            event(new VoteReceived(
                $gameId,
                $votingType,
                $voterId,
                $targetId,
                $voteCounts
            ));

            return response()->json([
                'success' => true,
                'data' => ['currentVotes' => $voteCounts],
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
     * Resolver votación y eliminar al jugador más votado
     */
    public function resolveVoting(Request $request, $gameId)
    {
        try {
            $votingType = $request->input('voting_type', 'village');
            
            $gameState = $this->getGameStateFromCache($gameId);
            $votes = $gameState['votes'] ?? [];

            if (empty($votes)) {
                event(new VotingEnded($gameId, $votingType, null, null, []));
                event(new GameMessage($gameId, 'No hubo votos. Nadie fue eliminado.', 'system'));
                
                return response()->json([
                    'success' => true,
                    'message' => 'No hubo votos'
                ], 200);
            }

            // Contar votos
            $voteCounts = array_count_values($votes);
            arsort($voteCounts);
            
            // Obtener el más votado
            $eliminatedId = array_key_first($voteCounts);
            $maxVotes = $voteCounts[$eliminatedId];

            // Verificar empate
            $tied = array_filter($voteCounts, fn($v) => $v === $maxVotes);
            if (count($tied) > 1) {
                // En caso de empate, elegir aleatorio entre los empatados
                $eliminatedId = array_rand($tied);
            }

            // Obtener datos del jugador eliminado
            $eliminated = GameLobby::where('id_game', $gameId)
                ->where('id_user', $eliminatedId)
                ->with('user:id,nickname')
                ->first();

            if ($eliminated) {
                // Marcar como muerto
                $eliminated->is_alive = 0;
                $eliminated->save();

                $cause = $votingType === 'wolves' ? 'wolves' : 'village';

                event(new VotingEnded(
                    $gameId,
                    $votingType,
                    $eliminatedId,
                    $eliminated->user->nickname,
                    $voteCounts
                ));

                event(new PlayerKilled(
                    $gameId,
                    $eliminatedId,
                    $eliminated->user->nickname,
                    $cause
                ));

                $emoji = $votingType === 'wolves' ? '🐺' : '⚖️';
                event(new GameMessage(
                    $gameId,
                    "{$emoji} {$eliminated->user->nickname} ha sido eliminado con {$maxVotes} votos",
                    'system'
                ));

                // Verificar condición de victoria
                $this->checkWinCondition($gameId);
            }

            // Limpiar votos
            $gameState['votes'] = [];
            Cache::put("game_state_{$gameId}", $gameState, 3600);

            return response()->json([
                'success' => true,
                'data' => [
                    'eliminatedId' => $eliminatedId,
                    'eliminatedNick' => $eliminated->user->nickname ?? null,
                    'votes' => $maxVotes
                ],
                'message' => 'Votación resuelta'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== ACCIONES DE PERSONAJES (VACÍAS PARA RELLENAR) ====================

    /**
     * Acción de Cupido - Enamorar a 2 jugadores
     */
    public function cupidAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica de Cupido
        // - Recibir player1_id y player2_id
        // - Guardar en gameState['lovers']
        // - Si uno muere, el otro también
        return response()->json(['success' => true, 'message' => 'Cupido action placeholder']);
    }

    /**
     * Acción del Ladrón - Intercambiar rol
     */
    public function thiefAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica del Ladrón
        // - Solo 1 uso
        // - Intercambiar personaje con otro jugador
        return response()->json(['success' => true, 'message' => 'Thief action placeholder']);
    }

    /**
     * Acción del Protector - Proteger a un jugador
     */
    public function protectorAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica del Protector
        // - No puede proteger al mismo 2 veces seguidas
        // - El jugador protegido no puede morir esa noche
        return response()->json(['success' => true, 'message' => 'Protector action placeholder']);
    }

    /**
     * Acción de la Vidente - Ver rol de un jugador
     */
    public function seerAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica de la Vidente
        // - Revelar el personaje de un jugador al cliente
        return response()->json(['success' => true, 'message' => 'Seer action placeholder']);
    }

    /**
     * Acción de la Bruja - Usar poción
     */
    public function witchAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica de la Bruja
        // - Poción de vida: salvar a la víctima de los lobos
        // - Poción de muerte: matar a un jugador
        // - Solo 1 uso de cada una
        return response()->json(['success' => true, 'message' => 'Witch action placeholder']);
    }

    /**
     * Acción del Cazador - Disparar al morir
     */
    public function hunterAction(Request $request, $gameId)
    {
        // TODO: Implementar lógica del Cazador
        // - Al morir, puede matar a otro jugador
        return response()->json(['success' => true, 'message' => 'Hunter action placeholder']);
    }

    // ==================== MÉTODOS AUXILIARES ====================

    /**
     * Obtener estado del juego desde cache
     */
    private function getGameStateFromCache($gameId): array
    {
        return Cache::get("game_state_{$gameId}", [
            'phase' => 'waiting',
            'turn' => 0,
            'votes' => [],
            'wolfVictim' => null,
            'protectedPlayer' => null,
            'lastProtected' => null,
            'lovers' => [],
            'thiefUsed' => false,
            'witchLifeUsed' => false,
            'witchDeathUsed' => false,
        ]);
    }

    /**
     * Determinar la siguiente fase
     */
    private function getNextPhase(string $currentPhase, int $turn): string
    {
        $phases = [
            'cupido' => 'thief',      // Solo turno 1
            'thief' => 'protector',
            'protector' => 'seer',
            'seer' => 'wolves',
            'wolves' => 'witch',
            'witch' => 'day',
            'day' => 'thief',         // Nueva noche
        ];

        // Si es turno 1 y estamos en cupido, ir a thief
        // Si es turno > 1, saltar cupido
        if ($currentPhase === 'waiting') {
            return $turn === 1 ? 'cupido' : 'thief';
        }

        return $phases[$currentPhase] ?? 'day';
    }

    /**
     * Obtener datos de una fase
     */
    private function getPhaseData(string $phase, int $gameId, array $gameState): array
    {
        $durations = [
            'cupido' => self::PHASE_CUPIDO,
            'thief' => self::PHASE_THIEF_ASK,
            'protector' => self::PHASE_PROTECTOR_ASK,
            'seer' => self::PHASE_SEER_ASK,
            'wolves' => self::PHASE_WOLVES,
            'witch' => self::PHASE_WITCH_ASK,
            'day' => self::PHASE_VILLAGE,
        ];

        $messages = [
            'cupido' => '💘 Cupido elige a los enamorados...',
            'thief' => '🎭 El Ladrón puede intercambiar su rol...',
            'protector' => '🛡️ El Protector elige a quién proteger...',
            'seer' => '🔮 La Vidente investiga...',
            'wolves' => '🐺 Los Lobos eligen a su víctima...',
            'witch' => '🧙‍♀️ La Bruja decide usar sus pociones...',
            'day' => '☀️ Amanece en el pueblo. ¡Es hora de votar!',
        ];

        return [
            'duration' => $durations[$phase] ?? 30,
            'data' => [
                'message' => $messages[$phase] ?? '',
            ]
        ];
    }

    /**
     * Obtener jugadores vivos de un rol específico
     */
    private function getAlivePlayers(int $gameId, string $roleName)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->whereHas('character', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            })
            ->get();
    }

    /**
     * Obtener jugadores vivos excepto ciertos IDs
     */
    private function getAlivePlayersExcept(int $gameId, array $excludeIds)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->whereNotIn('id_user', $excludeIds)
            ->get();
    }

    /**
     * Obtener todos los jugadores vivos
     */
    private function getAllAlivePlayers(int $gameId)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->get();
    }

    /**
     * Verificar condición de victoria
     */
    private function checkWinCondition(int $gameId): void
    {
        $alivePlayers = GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->with(['character', 'user:id,nickname'])
            ->get();

        $wolves = $alivePlayers->filter(fn($p) => $p->character->name === self::ROLE_WOLF);
        $villagers = $alivePlayers->filter(fn($p) => $p->character->name !== self::ROLE_WOLF);

        $winner = null;

        // Los lobos ganan si son igual o más que los aldeanos
        if ($wolves->count() >= $villagers->count() && $wolves->count() > 0) {
            $winner = 'wolves';
        }
        
        // Los aldeanos ganan si no quedan lobos
        if ($wolves->count() === 0 && $villagers->count() > 0) {
            $winner = 'village';
        }

        if ($winner) {
            $survivors = $alivePlayers->map(fn($p) => [
                'id' => $p->id_user,
                'nick' => $p->user->nickname,
                'role' => $p->character->name,
            ])->toArray();

            event(new GameEnded($gameId, $winner, $survivors));

            $winnerText = $winner === 'wolves' ? '🐺 ¡Los Lobos han ganado!' : '🏘️ ¡Los Aldeanos han ganado!';
            event(new GameMessage($gameId, $winnerText, 'system'));
        }
    }
}
