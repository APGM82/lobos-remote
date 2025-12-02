<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GameLobby;
use App\Events\GamePhaseChanged;
use App\Events\GameMessage;
use App\Events\VotingStarted;
use App\Events\PlayerKilled;
use App\Events\GameEnded;
use Illuminate\Support\Facades\Cache;

/**
 * Servicio para manejar el flujo del juego
 * 
 * FASES DEL JUEGO:
 * - cupido: Cupido elige enamorados (20s) - Solo primera noche
 * - thief: Ladrón puede intercambiar rol (15s)
 * - protector: Protector protege a alguien (15s)
 * - seer: Vidente investiga (15s)
 * - wolves: Lobos votan víctima (60s)
 * - witch: Bruja usa pociones (20s)
 * - day: Aldeanos votan linchamiento (120s)
 */
class GameFlowService
{
    // ==================== CONSTANTES ====================
    
    // Tiempos de fases (en segundos)
    const PHASE_CUPIDO = 20;
    const PHASE_THIEF = 15;
    const PHASE_PROTECTOR = 15;
    const PHASE_SEER = 15;
    const PHASE_WOLVES = 60;
    const PHASE_WITCH = 20;
    const PHASE_DAY = 120;

    // Nombres de personajes
    const ROLE_WOLF = 'Hombre Lobo';
    const ROLE_VILLAGER = 'Aldeano';
    const ROLE_SEER = 'Vidente';
    const ROLE_WITCH = 'Bruja';
    const ROLE_HUNTER = 'Cazador';
    const ROLE_CUPID = 'Cupido';
    const ROLE_THIEF = 'Ladrón';
    const ROLE_PROTECTOR = 'Protector';
    const ROLE_GIRL = 'Niña';

    // Mensajes de fases
    const PHASE_MESSAGES = [
        'cupido' => 'Cupido despierta y elige a los enamorados...',
        'thief' => 'El Ladrón puede intercambiar su rol...',
        'protector' => 'El Protector elige a quién proteger...',
        'seer' => 'La Vidente investiga a un jugador...',
        'wolves' => 'Los Lobos despiertan y eligen a su víctima...',
        'witch' => 'La Bruja decide si usar sus pociones...',
        'day' => 'Amanece en el pueblo. ¡Es hora de votar!',
    ];

    // ==================== INICIAR JUEGO ====================

    /**
     * Iniciar el flujo del juego
     */
    public function startGame(Game $game): array
    {
        // Inicializar estado del juego en cache
        $gameState = $this->createInitialState();
        $this->saveGameState($game->id, $gameState);

        // Emitir eventos de inicio
        $this->emitPhaseChange($game, 'cupido', false);
        $this->emitMessage($game->id, '🌙 La noche cae sobre el pueblo...');
        $this->emitMessage($game->id, self::PHASE_MESSAGES['cupido']);

        return [
            'phase' => 'cupido',
            'turn' => 1,
            'duration' => self::PHASE_CUPIDO,
        ];
    }

    /**
     * Crear estado inicial del juego
     */
    private function createInitialState(): array
    {
        return [
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
            'witchKill' => null,
            'witchSavedVictim' => false,
        ];
    }

    // ==================== AVANZAR FASE ====================

    /**
     * Avanzar a la siguiente fase
     */
    public function nextPhase(Game $game): array
    {
        $gameState = $this->getGameState($game->id);
        $currentPhase = $gameState['phase'];
        $turn = $gameState['turn'];

        // Determinar siguiente fase
        $nextPhase = $this->getNextPhase($currentPhase, $turn);

        // Si es nueva noche, incrementar turno
        if ($currentPhase === 'day' && $nextPhase === 'thief') {
            $turn++;
            $this->emitMessage($game->id, "Cae la noche {$turn} sobre el pueblo...");
        }

        // Actualizar estado
        $gameState['phase'] = $nextPhase;
        $gameState['turn'] = $turn;
        
        // Limpiar votos al cambiar de fase
        $gameState['votes'] = [];
        
        $this->saveGameState($game->id, $gameState);

        // Determinar si hay votación en la nueva fase
        $votingInProgress = ($nextPhase === 'day' || $nextPhase === 'wolves');

        // Emitir evento de cambio de fase
        $this->emitPhaseChange($game, $nextPhase, $votingInProgress);
        $this->emitMessage($game->id, self::PHASE_MESSAGES[$nextPhase] ?? "Fase: {$nextPhase}");

        // Si es fase de lobos, iniciar votación de lobos
        if ($nextPhase === 'wolves') {
            $this->startWolvesVoting($game->id);
        }

        // Si es fase de día, iniciar votación del pueblo
        if ($nextPhase === 'day') {
            // Primero aplicar muertes de la noche
            $this->applyNightDeaths($game->id);
            $this->startVillageVoting($game->id);
        }

        return [
            'phase' => $nextPhase,
            'turn' => $turn,
            'duration' => $this->getPhaseDuration($nextPhase),
        ];
    }

    /**
     * Determinar la siguiente fase
     */
    private function getNextPhase(string $currentPhase, int $turn): string
    {
        $phases = [
            'cupido' => 'thief',
            'thief' => 'protector',
            'protector' => 'seer',
            'seer' => 'wolves',
            'wolves' => 'witch',
            'witch' => 'day',
            'day' => 'thief', // Nueva noche (sin cupido)
        ];

        // Si es turno > 1, saltar cupido
        if ($currentPhase === 'waiting') {
            return $turn === 1 ? 'cupido' : 'thief';
        }

        return $phases[$currentPhase] ?? 'day';
    }

    /**
     * Obtener duración de una fase
     */
    public function getPhaseDuration(string $phase): int
    {
        $durations = [
            'cupido' => self::PHASE_CUPIDO,
            'thief' => self::PHASE_THIEF,
            'protector' => self::PHASE_PROTECTOR,
            'seer' => self::PHASE_SEER,
            'wolves' => self::PHASE_WOLVES,
            'witch' => self::PHASE_WITCH,
            'day' => self::PHASE_DAY,
        ];

        return $durations[$phase] ?? 30;
    }

    // ==================== VOTACIONES ====================

    /**
     * Iniciar votación de lobos
     */
    public function startWolvesVoting(int $gameId): void
    {
        $gameState = $this->getGameState($gameId);
        $gameState['votes'] = [];
        $gameState['votingType'] = 'wolves';
        $this->saveGameState($gameId, $gameState);

        // Obtener lobos vivos
        $wolves = $this->getAlivePlayers($gameId, self::ROLE_WOLF);
        $wolfIds = $wolves->pluck('id_user')->toArray();

        // Obtener posibles víctimas (todos excepto lobos)
        $targets = $this->getAlivePlayersExcept($gameId, $wolfIds);
        $targetIds = $targets->pluck('id_user')->toArray();

        event(new VotingStarted($gameId, 'wolves', self::PHASE_WOLVES, $wolfIds, $targetIds));
    }

    /**
     * Iniciar votación del pueblo
     */
    public function startVillageVoting(int $gameId): void
    {
        $gameState = $this->getGameState($gameId);
        $gameState['votes'] = [];
        $gameState['votingType'] = 'village';
        $this->saveGameState($gameId, $gameState);

        // Obtener todos los jugadores vivos
        $alivePlayers = $this->getAllAlivePlayers($gameId);
        $playerIds = $alivePlayers->pluck('id_user')->toArray();

        event(new VotingStarted($gameId, 'village', self::PHASE_DAY, $playerIds, $playerIds));
    }

    // ==================== MUERTES NOCTURNAS ====================

    /**
     * Aplicar las muertes de la noche
     */
    public function applyNightDeaths(int $gameId): array
    {
        $gameState = $this->getGameState($gameId);
        $deaths = [];

        $wolfVictimId = $gameState['wolfVictim'] ?? null;
        $protectedId = $gameState['protectedPlayer'] ?? null;
        $witchSaved = $gameState['witchSavedVictim'] ?? false;
        $witchKillId = $gameState['witchKill'] ?? null;

        // Víctima de los lobos (si no fue protegida ni salvada)
        if ($wolfVictimId && $wolfVictimId !== $protectedId && !$witchSaved) {
            $death = $this->killPlayer($gameId, $wolfVictimId, 'wolves');
            if ($death) {
                $deaths[] = $death;
            }
        } elseif ($wolfVictimId && ($wolfVictimId === $protectedId || $witchSaved)) {
            $this->emitMessage($gameId, "Alguien fue atacado por los lobos pero sobrevivió...");
        }

        // Víctima de la bruja (poción de muerte)
        if ($witchKillId) {
            $death = $this->killPlayer($gameId, $witchKillId, 'witch');
            if ($death) {
                $deaths[] = $death;
            }
        }

        // Verificar enamorados
        $deaths = array_merge($deaths, $this->checkLoversDeaths($gameId, $deaths, $gameState));

        // Limpiar estado de la noche
        $gameState['wolfVictim'] = null;
        $gameState['protectedPlayer'] = null;
        $gameState['witchSavedVictim'] = false;
        $gameState['witchKill'] = null;
        $this->saveGameState($gameId, $gameState);

        // Verificar condición de victoria
        $this->checkWinCondition($gameId);

        if (count($deaths) === 0) {
            $this->emitMessage($gameId, "☀️ Amanece... ¡Nadie murió durante la noche!");
        }

        return $deaths;
    }

    /**
     * Matar a un jugador
     */
    private function killPlayer(int $gameId, int $playerId, string $cause): ?array
    {
        $player = GameLobby::where('id_game', $gameId)
            ->where('id_user', $playerId)
            ->with(['user:id,nickname', 'character:id,name'])
            ->first();

        if (!$player || !$player->is_alive) {
            return null;
        }

        $player->is_alive = 0;
        $player->save();

        $causeMessages = [
            'wolves' => "fue devorado por los lobos durante la noche",
            'witch' => "murió misteriosamente durante la noche",
            'village' => "fue linchado por el pueblo",
            'lovers' => "murió de pena al perder a su amor",
        ];

        event(new PlayerKilled($gameId, $playerId, $player->user->nickname, $cause));
        $this->emitMessage($gameId, "💀 {$player->user->nickname} {$causeMessages[$cause]}");

        return [
            'id' => $playerId,
            'nick' => $player->user->nickname,
            'role' => $player->character->name ?? null,
            'cause' => $cause,
        ];
    }

    /**
     * Verificar muertes de enamorados
     */
    private function checkLoversDeaths(int $gameId, array $deaths, array $gameState): array
    {
        $additionalDeaths = [];
        $lovers = $gameState['lovers'] ?? [];

        if (empty($lovers) || empty($deaths)) {
            return $additionalDeaths;
        }

        foreach ($deaths as $death) {
            if (in_array($death['id'], $lovers)) {
                $otherLoverId = $lovers[0] == $death['id'] ? ($lovers[1] ?? null) : $lovers[0];
                
                if ($otherLoverId) {
                    $loverDeath = $this->killPlayer($gameId, $otherLoverId, 'lovers');
                    if ($loverDeath) {
                        $additionalDeaths[] = $loverDeath;
                    }
                }
                break;
            }
        }

        return $additionalDeaths;
    }

    // ==================== CONDICIÓN DE VICTORIA ====================

    /**
     * Verificar condición de victoria
     */
    public function checkWinCondition(int $gameId): ?string
    {
        $alivePlayers = GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->with(['character', 'user:id,nickname'])
            ->get();

        $wolves = $alivePlayers->filter(fn($p) => $p->character && $p->character->name === self::ROLE_WOLF);
        $villagers = $alivePlayers->filter(fn($p) => !$p->character || $p->character->name !== self::ROLE_WOLF);

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
                'role' => $p->character->name ?? null,
            ])->toArray();

            event(new GameEnded($gameId, $winner, $survivors));

            $winnerText = $winner === 'wolves' 
                ? '🐺 ¡Los Lobos han ganado!' 
                : '👨‍🌾 ¡Los Aldeanos han ganado!';
            $this->emitMessage($gameId, $winnerText);

            // Marcar juego como finalizado
            $this->endGame($gameId);
        }

        return $winner;
    }

    /**
     * Finalizar el juego
     */
    private function endGame(int $gameId): void
    {
        $game = Game::find($gameId);
        if ($game) {
            $finishedStatus = \App\Models\StatusCode::where('name', 'finalizada')->first();
            if ($finishedStatus) {
                $game->code_status = $finishedStatus->id;
                $game->save();
            }
        }

        // Limpiar cache del juego
        Cache::forget("game_state_{$gameId}");
    }

    // ==================== HELPERS ====================

    /**
     * Obtener estado del juego desde cache
     */
    public function getGameState(int $gameId): array
    {
        return Cache::get("game_state_{$gameId}", $this->createInitialState());
    }

    /**
     * Guardar estado del juego en cache
     */
    public function saveGameState(int $gameId, array $state): void
    {
        Cache::put("game_state_{$gameId}", $state, 3600); // 1 hora
    }

    /**
     * Emitir evento de cambio de fase
     */
    private function emitPhaseChange(Game $game, string $phase, bool $votingInProgress): void
    {
        event(new GamePhaseChanged(
            $game,
            $phase,
            self::PHASE_MESSAGES[$phase] ?? "Fase: {$phase}",
            $this->getPhaseDuration($phase),
            $votingInProgress
        ));
    }

    /**
     * Emitir mensaje del sistema
     */
    private function emitMessage(int $gameId, string $message): void
    {
        event(new GameMessage($gameId, $message, 'system'));
    }

    /**
     * Obtener jugadores vivos de un rol específico
     */
    public function getAlivePlayers(int $gameId, string $roleName)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->whereHas('character', fn($q) => $q->where('name', $roleName))
            ->with(['user:id,nickname', 'character:id,name'])
            ->get();
    }

    /**
     * Obtener jugadores vivos excepto ciertos IDs
     */
    public function getAlivePlayersExcept(int $gameId, array $excludeIds)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->whereNotIn('id_user', $excludeIds)
            ->with(['user:id,nickname', 'character:id,name'])
            ->get();
    }

    /**
     * Obtener todos los jugadores vivos
     */
    public function getAllAlivePlayers(int $gameId)
    {
        return GameLobby::where('id_game', $gameId)
            ->where('is_alive', 1)
            ->with(['user:id,nickname', 'character:id,name'])
            ->get();
    }
}
