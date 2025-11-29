/**
 * Motor del juego - Maneja la lógica de fases, temporizadores y votaciones
 */

import { WebSocketClient } from './WebSocketClient';
import type {
    GamePhase,
    VotingType,
    Player,
    GameState,
    PhaseChangedEvent,
    VotingStartedEvent,
    VoteReceivedEvent,
    VotingEndedEvent,
    PlayerKilledEvent,
    GameMessageEvent,
    GameEndedEvent,
} from './types';

const API_URL = 'http://127.0.0.1:8000/api';

// Callbacks para la UI
export interface GameEngineCallbacks {
    onPhaseChanged: (phase: GamePhase, message: string, duration: number) => void;
    onTimerUpdate: (seconds: number) => void;
    onVotingStarted: (votingType: VotingType, canVote: boolean, targets: number[]) => void;
    onVotesUpdated: (votes: Record<number, number>) => void;
    onPlayerKilled: (playerId: number, nick: string, cause: string) => void;
    onMessage: (message: string, type: string) => void;
    onPlayersUpdated: (players: Player[]) => void;
    onGameEnded: (winner: string, survivors: any[]) => void;
    onConnectionChanged: (connected: boolean) => void;
}

/**
 * Motor principal del juego
 */
export class GameEngine {
    private gameId: number;
    private wsClient: WebSocketClient;
    private callbacks: GameEngineCallbacks;
    
    // Estado del juego
    private state: GameState;
    private timer: number | null = null;
    private timeRemaining: number = 0;
    
    // Estado de votación
    private hasVoted: boolean = false;
    private eligibleTargets: number[] = [];

    constructor(gameId: number, callbacks: GameEngineCallbacks) {
        this.gameId = gameId;
        this.callbacks = callbacks;
        
        // Inicializar estado
        this.state = {
            gameId,
            gameName: '',
            phase: 'waiting',
            turn: 0,
            players: [],
            currentPlayerId: 0,
            isAlive: true,
            timeRemaining: 0,
            canVote: false,
            canAct: false,
        };

        // Crear cliente WebSocket
        this.wsClient = new WebSocketClient(gameId);
        this.setupWebSocketCallbacks();
    }

    /**
     * Inicializar el motor del juego
     */
    public async init(): Promise<void> {
        // Cargar estado inicial del servidor
        await this.loadGameState();
        
        // Conectar WebSocket
        this.wsClient.connect();
    }

    /**
     * Configurar callbacks del WebSocket
     */
    private setupWebSocketCallbacks(): void {
        this.wsClient.setOnConnected(() => {
            this.callbacks.onConnectionChanged(true);
            this.callbacks.onMessage('✅ Conectado al servidor', 'system');
        });

        this.wsClient.setOnDisconnected(() => {
            this.callbacks.onConnectionChanged(false);
            this.callbacks.onMessage('🔌 Desconectado del servidor', 'system');
        });

        this.wsClient.setOnError((error) => {
            this.callbacks.onMessage(`⚠️ Error de conexión: ${error.message || 'desconocido'}`, 'system');
        });

        this.wsClient.setOnPhaseChanged((data: PhaseChangedEvent) => {
            this.handlePhaseChanged(data);
        });

        this.wsClient.setOnVotingStarted((data: VotingStartedEvent) => {
            this.handleVotingStarted(data);
        });

        this.wsClient.setOnVoteReceived((data: VoteReceivedEvent) => {
            this.handleVoteReceived(data);
        });

        this.wsClient.setOnVotingEnded((data: VotingEndedEvent) => {
            this.handleVotingEnded(data);
        });

        this.wsClient.setOnPlayerKilled((data: PlayerKilledEvent) => {
            this.handlePlayerKilled(data);
        });

        this.wsClient.setOnGameMessage((data: GameMessageEvent) => {
            this.callbacks.onMessage(data.message, data.type);
        });

        this.wsClient.setOnGameEnded((data: GameEndedEvent) => {
            this.handleGameEnded(data);
        });
    }

    /**
     * Cargar estado del juego desde la API
     */
    private async loadGameState(): Promise<void> {
        try {
            const response = await fetch(`${API_URL}/gameplay/${this.gameId}/state`);
            const result = await response.json();

            if (result.success) {
                const data = result.data;
                this.state.gameName = data.gameName;
                this.state.phase = data.phase;
                this.state.turn = data.turn;
                this.state.players = data.players;
                this.state.timeRemaining = data.timeRemaining;

                // Determinar mi personaje (el servidor debería enviarlo si estoy autenticado)
                const me = this.state.players.find(p => p.id === this.state.currentPlayerId);
                if (me) {
                    this.state.myCharacter = me.character;
                    this.state.isAlive = me.isAlive;
                }

                this.callbacks.onPlayersUpdated(this.state.players);
            }
        } catch (error) {
            console.error('Error cargando estado:', error);
            this.callbacks.onMessage('Error cargando estado del juego', 'system');
        }
    }

    /**
     * Manejar cambio de fase
     */
    private handlePhaseChanged(data: PhaseChangedEvent): void {
        this.state.phase = data.phase;
        this.state.turn = data.turn;
        this.timeRemaining = data.duration;
        this.hasVoted = false;

        // Notificar a la UI
        this.callbacks.onPhaseChanged(
            data.phase,
            data.data.message || '',
            data.duration
        );

        // Iniciar temporizador
        this.startTimer(data.duration);
    }

    /**
     * Manejar inicio de votación
     */
    private handleVotingStarted(data: VotingStartedEvent): void {
        this.hasVoted = false;
        this.eligibleTargets = data.eligibleTargets;

        // Determinar si puedo votar
        const canVote = data.eligibleVoters.includes(this.state.currentPlayerId) && this.state.isAlive;
        
        this.state.canVote = canVote;

        this.callbacks.onVotingStarted(data.votingType, canVote, data.eligibleTargets);
        
        // Iniciar temporizador de votación
        this.startTimer(data.duration);
    }

    /**
     * Manejar voto recibido
     */
    private handleVoteReceived(data: VoteReceivedEvent): void {
        // Actualizar votos en los jugadores
        this.state.players.forEach(player => {
            player.votes = data.currentVotes[player.id] || 0;
        });

        this.callbacks.onVotesUpdated(data.currentVotes);
        this.callbacks.onPlayersUpdated(this.state.players);
    }

    /**
     * Manejar fin de votación
     */
    private handleVotingEnded(data: VotingEndedEvent): void {
        this.state.canVote = false;
        this.stopTimer();

        if (data.eliminatedPlayerId && data.eliminatedPlayerNick) {
            const emoji = data.votingType === 'wolves' ? '🐺' : '⚖️';
            this.callbacks.onMessage(
                `${emoji} ${data.eliminatedPlayerNick} ha sido eliminado`,
                'system'
            );
        } else {
            this.callbacks.onMessage('No hubo consenso. Nadie fue eliminado.', 'system');
        }

        // Limpiar votos visuales
        this.state.players.forEach(player => player.votes = 0);
        this.callbacks.onPlayersUpdated(this.state.players);
    }

    /**
     * Manejar jugador muerto
     */
    private handlePlayerKilled(data: PlayerKilledEvent): void {
        // Actualizar estado del jugador
        const player = this.state.players.find(p => p.id === data.playerId);
        if (player) {
            player.isAlive = false;
        }

        // Si soy yo el que murió
        if (data.playerId === this.state.currentPlayerId) {
            this.state.isAlive = false;
            this.callbacks.onMessage('💀 ¡Has sido eliminado! Ya no puedes realizar acciones.', 'system');
        }

        this.callbacks.onPlayerKilled(data.playerId, data.playerNick, data.cause);
        this.callbacks.onPlayersUpdated(this.state.players);
    }

    /**
     * Manejar fin del juego
     */
    private handleGameEnded(data: GameEndedEvent): void {
        this.stopTimer();
        this.state.phase = 'waiting';

        const winnerText = data.winner === 'wolves' 
            ? '🐺 ¡Los Lobos han ganado!' 
            : '🏘️ ¡Los Aldeanos han ganado!';

        this.callbacks.onMessage(winnerText, 'system');
        this.callbacks.onGameEnded(data.winner, data.survivors);
    }

    /**
     * Iniciar temporizador
     */
    private startTimer(seconds: number): void {
        this.stopTimer();
        this.timeRemaining = seconds;
        
        this.callbacks.onTimerUpdate(this.timeRemaining);

        this.timer = window.setInterval(() => {
            this.timeRemaining--;
            this.callbacks.onTimerUpdate(this.timeRemaining);

            if (this.timeRemaining <= 0) {
                this.stopTimer();
            }
        }, 1000);
    }

    /**
     * Detener temporizador
     */
    private stopTimer(): void {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    // ==================== ACCIONES DEL JUGADOR ====================

    /**
     * Votar por un jugador
     */
    public async vote(targetId: number): Promise<boolean> {
        if (this.hasVoted || !this.state.canVote || !this.state.isAlive) {
            return false;
        }

        if (!this.eligibleTargets.includes(targetId)) {
            this.callbacks.onMessage('No puedes votar a este jugador', 'system');
            return false;
        }

        try {
            const votingType = this.state.phase === 'wolves' ? 'wolves' : 'village';
            
            const response = await fetch(`${API_URL}/gameplay/${this.gameId}/vote`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    voter_id: this.state.currentPlayerId,
                    target_id: targetId,
                    voting_type: votingType
                })
            });

            const result = await response.json();
            
            if (result.success) {
                this.hasVoted = true;
                const target = this.state.players.find(p => p.id === targetId);
                this.callbacks.onMessage(`Has votado por ${target?.nick || 'jugador'}`, 'action');
                return true;
            }
        } catch (error) {
            console.error('Error al votar:', error);
            this.callbacks.onMessage('Error al enviar el voto', 'system');
        }

        return false;
    }

    /**
     * Verificar si puedo votar a un jugador
     */
    public canVoteFor(targetId: number): boolean {
        if (this.hasVoted || !this.state.canVote || !this.state.isAlive) {
            return false;
        }
        
        // No puedo votarme a mí mismo
        if (targetId === this.state.currentPlayerId) {
            return false;
        }

        // Verificar si es un objetivo válido
        return this.eligibleTargets.includes(targetId);
    }

    /**
     * Verificar si soy lobo (para ver a otros lobos)
     */
    public isWolf(): boolean {
        return this.state.myCharacter === 'Hombre Lobo';
    }

    /**
     * Obtener lista de lobos (solo si soy lobo)
     */
    public getWolves(): Player[] {
        if (!this.isWolf()) return [];
        return this.state.players.filter(p => 
            p.character === 'Hombre Lobo' && p.isAlive
        );
    }

    // ==================== GETTERS ====================

    public getState(): GameState {
        return { ...this.state };
    }

    public getPlayers(): Player[] {
        return [...this.state.players];
    }

    public getCurrentPhase(): GamePhase {
        return this.state.phase;
    }

    public getTurn(): number {
        return this.state.turn;
    }

    public getTimeRemaining(): number {
        return this.timeRemaining;
    }

    public isAlive(): boolean {
        return this.state.isAlive;
    }

    public hasPlayerVoted(): boolean {
        return this.hasVoted;
    }

    // ==================== SETTERS ====================

    public setCurrentPlayerId(playerId: number): void {
        this.state.currentPlayerId = playerId;
        const me = this.state.players.find(p => p.id === playerId);
        if (me) {
            this.state.myCharacter = me.character;
            this.state.isAlive = me.isAlive;
        }
    }

    // ==================== CLEANUP ====================

    public destroy(): void {
        this.stopTimer();
        this.wsClient.disconnect();
    }
}

export default GameEngine;
