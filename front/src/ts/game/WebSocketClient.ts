/**
 * Cliente WebSocket usando Pusher para conectar con Laravel Reverb
 * Maneja la conexión y los eventos del juego en tiempo real
 */

import type {
    PhaseChangedEvent,
    VotingStartedEvent,
    VoteReceivedEvent,
    VotingEndedEvent,
    PlayerKilledEvent,
    GameMessageEvent,
    ActionPromptEvent,
    GameEndedEvent,
} from './types';

// Declarar Pusher como global (se carga desde CDN)
declare const Pusher: any;

// Configuración de conexión
const WS_HOST = '127.0.0.1';
const WS_PORT = '8080';
const API_PORT = '8000';
const PUSHER_KEY = 'local-app-key';

// Tipos de callbacks para eventos
type PhaseChangedCallback = (data: PhaseChangedEvent) => void;
type VotingStartedCallback = (data: VotingStartedEvent) => void;
type VoteReceivedCallback = (data: VoteReceivedEvent) => void;
type VotingEndedCallback = (data: VotingEndedEvent) => void;
type PlayerKilledCallback = (data: PlayerKilledEvent) => void;
type GameMessageCallback = (data: GameMessageEvent) => void;
type ActionPromptCallback = (data: ActionPromptEvent) => void;
type GameEndedCallback = (data: GameEndedEvent) => void;
type ConnectionCallback = () => void;
type ErrorCallback = (error: any) => void;

/**
 * Cliente WebSocket para el juego
 */
export class WebSocketClient {
    private pusher: any;
    private channel: any;
    private gameId: number;
    private isConnected: boolean = false;

    // Callbacks de eventos
    private onPhaseChanged?: PhaseChangedCallback;
    private onVotingStarted?: VotingStartedCallback;
    private onVoteReceived?: VoteReceivedCallback;
    private onVotingEnded?: VotingEndedCallback;
    private onPlayerKilled?: PlayerKilledCallback;
    private onGameMessage?: GameMessageCallback;
    private onActionPrompt?: ActionPromptCallback;
    private onGameEnded?: GameEndedCallback;
    private onConnected?: ConnectionCallback;
    private onDisconnected?: ConnectionCallback;
    private onError?: ErrorCallback;

    constructor(gameId: number) {
        this.gameId = gameId;
    }

    /**
     * Conectar al servidor WebSocket
     */
    public connect(): void {
        // Verificar que Pusher esté cargado
        if (typeof Pusher === 'undefined') {
            console.error('Pusher no está cargado. Asegúrate de incluir el script de Pusher.');
            this.onError?.({ message: 'Pusher no está cargado' });
            return;
        }

        // Configurar Pusher
        this.pusher = new Pusher(PUSHER_KEY, {
            wsHost: WS_HOST,
            wsPort: WS_PORT,
            forceTLS: false,
            enabledTransports: ['ws'],
            cluster: 'mt1',
            disableStats: true,
            authEndpoint: `http://${WS_HOST}:${API_PORT}/broadcasting/auth`,
            auth: {
                headers: {
                    'Accept': 'application/json'
                }
            }
        });

        // Suscribirse al canal del juego
        this.channel = this.pusher.subscribe(`game.${this.gameId}`);

        // Eventos de conexión
        this.pusher.connection.bind('connected', () => {
            console.info('✅ Conectado a Reverb');
            this.isConnected = true;
            this.onConnected?.();
        });

        this.pusher.connection.bind('error', (err: any) => {
            console.error('⚠️ Error WebSocket:', err);
            this.isConnected = false;
            this.onError?.(err);
        });

        this.pusher.connection.bind('disconnected', () => {
            console.warn('🔌 Desconectado de Reverb');
            this.isConnected = false;
            this.onDisconnected?.();
        });

        // Vincular eventos del juego
        this.bindGameEvents();
    }

    /**
     * Vincular los eventos del juego al canal
     */
    private bindGameEvents(): void {
        // Cambio de fase
        this.channel.bind('phase.changed', (data: PhaseChangedEvent) => {
            console.log('📢 Fase cambiada:', data);
            this.onPhaseChanged?.(data);
        });

        // Inicio de votación
        this.channel.bind('voting.started', (data: VotingStartedEvent) => {
            console.log('🗳️ Votación iniciada:', data);
            this.onVotingStarted?.(data);
        });

        // Voto recibido
        this.channel.bind('vote.received', (data: VoteReceivedEvent) => {
            console.log('✋ Voto recibido:', data);
            this.onVoteReceived?.(data);
        });

        // Fin de votación
        this.channel.bind('voting.ended', (data: VotingEndedEvent) => {
            console.log('🏁 Votación terminada:', data);
            this.onVotingEnded?.(data);
        });

        // Jugador muerto
        this.channel.bind('player.killed', (data: PlayerKilledEvent) => {
            console.log('💀 Jugador eliminado:', data);
            this.onPlayerKilled?.(data);
        });

        // Mensaje del juego
        this.channel.bind('game.message', (data: GameMessageEvent) => {
            console.log('💬 Mensaje:', data);
            this.onGameMessage?.(data);
        });

        // Acción requerida
        this.channel.bind('action.prompt', (data: ActionPromptEvent) => {
            console.log('⚡ Acción requerida:', data);
            this.onActionPrompt?.(data);
        });

        // Fin del juego
        this.channel.bind('game.ended', (data: GameEndedEvent) => {
            console.log('🏆 Juego terminado:', data);
            this.onGameEnded?.(data);
        });
    }

    /**
     * Desconectar del servidor
     */
    public disconnect(): void {
        if (this.channel) {
            this.pusher.unsubscribe(`game.${this.gameId}`);
        }
        if (this.pusher) {
            this.pusher.disconnect();
        }
        this.isConnected = false;
    }

    /**
     * Verificar si está conectado
     */
    public getIsConnected(): boolean {
        return this.isConnected;
    }

    // ==================== SETTERS DE CALLBACKS ====================

    public setOnPhaseChanged(callback: PhaseChangedCallback): void {
        this.onPhaseChanged = callback;
    }

    public setOnVotingStarted(callback: VotingStartedCallback): void {
        this.onVotingStarted = callback;
    }

    public setOnVoteReceived(callback: VoteReceivedCallback): void {
        this.onVoteReceived = callback;
    }

    public setOnVotingEnded(callback: VotingEndedCallback): void {
        this.onVotingEnded = callback;
    }

    public setOnPlayerKilled(callback: PlayerKilledCallback): void {
        this.onPlayerKilled = callback;
    }

    public setOnGameMessage(callback: GameMessageCallback): void {
        this.onGameMessage = callback;
    }

    public setOnActionPrompt(callback: ActionPromptCallback): void {
        this.onActionPrompt = callback;
    }

    public setOnGameEnded(callback: GameEndedCallback): void {
        this.onGameEnded = callback;
    }

    public setOnConnected(callback: ConnectionCallback): void {
        this.onConnected = callback;
    }

    public setOnDisconnected(callback: ConnectionCallback): void {
        this.onDisconnected = callback;
    }

    public setOnError(callback: ErrorCallback): void {
        this.onError = callback;
    }
}

export default WebSocketClient;
