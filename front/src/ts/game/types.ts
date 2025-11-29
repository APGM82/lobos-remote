/**
 * Tipos y interfaces para el sistema de juego
 */

// Fases del juego
export type GamePhase = 
    | 'waiting'    // Esperando inicio
    | 'cupido'     // Cupido elige enamorados (solo turno 1)
    | 'thief'      // Ladrón puede robar rol
    | 'protector'  // Protector protege
    | 'seer'       // Vidente ve rol
    | 'wolves'     // Lobos votan víctima
    | 'witch'      // Bruja usa pociones
    | 'day';       // Día - votación aldeanos

// Tipos de votación
export type VotingType = 'wolves' | 'village';

// Tipos de mensaje
export type MessageType = 'system' | 'chat' | 'wolves_chat' | 'action';

// Roles de personajes
export type CharacterRole = 
    | 'Hombre Lobo'
    | 'Aldeano'
    | 'Vidente'
    | 'Bruja'
    | 'Cazador'
    | 'Cupido'
    | 'Ladrón'
    | 'Protector'
    | 'Niña';

// Causa de muerte
export type DeathCause = 'wolves' | 'village' | 'witch' | 'hunter' | 'lovers';

// Ganador
export type Winner = 'wolves' | 'village' | 'lovers';

// Interfaz de jugador
export interface Player {
    id: number;
    lobbyId: number;
    nick: string;
    isAlive: boolean;
    character?: CharacterRole;
    votes?: number;
    isWolf?: boolean;
    isLover?: boolean;
}

// Estado del juego
export interface GameState {
    gameId: number;
    gameName: string;
    phase: GamePhase;
    turn: number;
    players: Player[];
    currentPlayerId: number;
    myCharacter?: CharacterRole;
    isAlive: boolean;
    timeRemaining: number;
    canVote: boolean;
    canAct: boolean;
    wolves?: number[];        // IDs de los lobos (solo visible para lobos)
    lovers?: number[];        // IDs de los enamorados (solo visible para ellos)
    protectedPlayer?: number; // Jugador protegido esta noche
}

// Evento de cambio de fase
export interface PhaseChangedEvent {
    phase: GamePhase;
    turn: number;
    duration: number;
    data: {
        message?: string;
        [key: string]: unknown;
    };
    timestamp: string;
}

// Evento de inicio de votación
export interface VotingStartedEvent {
    votingType: VotingType;
    duration: number;
    eligibleVoters: number[];
    eligibleTargets: number[];
    timestamp: string;
}

// Evento de voto recibido
export interface VoteReceivedEvent {
    votingType: VotingType;
    voterId: number;
    targetId: number;
    currentVotes: Record<number, number>;
    timestamp: string;
}

// Evento de fin de votación
export interface VotingEndedEvent {
    votingType: VotingType;
    eliminatedPlayerId: number | null;
    eliminatedPlayerNick: string | null;
    finalVotes: Record<number, number>;
    timestamp: string;
}

// Evento de jugador muerto
export interface PlayerKilledEvent {
    playerId: number;
    playerNick: string;
    cause: DeathCause;
    timestamp: string;
}

// Evento de mensaje
export interface GameMessageEvent {
    message: string;
    type: MessageType;
    fromPlayerId: number | null;
    fromPlayerNick: string | null;
    timestamp: string;
}

// Evento de acción requerida
export interface ActionPromptEvent {
    targetPlayerId: number;
    actionType: string;
    duration: number;
    options: unknown[];
    canSkip: boolean;
    timestamp: string;
}

// Evento de fin de juego
export interface GameEndedEvent {
    winner: Winner;
    survivors: Array<{
        id: number;
        nick: string;
        role: CharacterRole;
    }>;
    timestamp: string;
}

// Configuración de tiempos de fases (en segundos)
export const PHASE_DURATIONS: Record<GamePhase, number> = {
    waiting: 0,
    cupido: 20,
    thief: 15,      // 5s pregunta + 10s acción
    protector: 15,  // 5s pregunta + 10s acción
    seer: 15,       // 5s pregunta + 10s acción
    wolves: 60,     // Chat y votación de lobos
    witch: 20,      // 5s pregunta + 5s elegir + 10s acción
    day: 120,       // 2 minutos de votación
};

// Mensajes de las fases
export const PHASE_MESSAGES: Record<GamePhase, string> = {
    waiting: 'Esperando jugadores...',
    cupido: '💘 Cupido elige a los enamorados...',
    thief: '🎭 El Ladrón puede intercambiar su rol...',
    protector: '🛡️ El Protector elige a quién proteger...',
    seer: '🔮 La Vidente investiga a un jugador...',
    wolves: '🐺 Los Lobos eligen a su víctima...',
    witch: '🧙‍♀️ La Bruja decide usar sus pociones...',
    day: '☀️ Es de día. ¡Los aldeanos deben votar!',
};
