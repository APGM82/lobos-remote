/**
 * Página del juego - Hombres Lobo de Castronegro
 * Integra el GameEngine con la interfaz de usuario
 */

import { GameEngine } from './GameEngine';
import type { GameEngineCallbacks } from './GameEngine';
import type { GamePhase, VotingType, Player } from './types';

const API_URL = 'http://127.0.0.1:8000/api';

/**
 * Clase principal para la página del juego
 */
class GamePage {
    private playersGrid: HTMLElement | null;
    private chatContainer: HTMLElement | null;
    private timerDisplay: HTMLElement | null;
    private phaseDisplay: HTMLElement | null;
    private connectionStatus: HTMLElement | null;

    private gameEngine: GameEngine | null = null;
    private gameId: number = 0;
    private currentPlayerId: number = 0;
    private useWebSocket: boolean = false;

    // Estado local (para modo demo sin WebSocket)
    private players: Player[] = [];
    private hasVoted: boolean = false;
    private votingInProgress: boolean = false;
    private isCurrentPlayerAlive: boolean = true;

    constructor() {
        this.playersGrid = document.getElementById('playersGrid');
        this.chatContainer = document.getElementById('chatContainer');
        this.timerDisplay = document.getElementById('timerDisplay');
        this.phaseDisplay = document.getElementById('phaseDisplay');
        this.connectionStatus = document.getElementById('connectionStatus');

        this.init();
    }

    /**
     * Inicializar la página
     */
    private async init(): Promise<void> {
        this.gameId = this.getGameIdFromUrl();
        this.currentPlayerId = this.getCurrentPlayerIdFromStorage();

        if (this.gameId && this.useWebSocket) {
            // Modo WebSocket: usar GameEngine
            await this.initWithWebSocket();
        } else {
            // Modo demo o sin WebSocket
            await this.initDemo();
        }
    }

    /**
     * Inicializar con WebSocket (GameEngine)
     */
    private async initWithWebSocket(): Promise<void> {
        const callbacks: GameEngineCallbacks = {
            onPhaseChanged: (phase, message, duration) => this.handlePhaseChanged(phase, message, duration),
            onTimerUpdate: (seconds) => this.updateTimer(seconds),
            onVotingStarted: (votingType, canVote, targets) => this.handleVotingStarted(votingType, canVote, targets),
            onVotesUpdated: (votes) => this.updateVotesDisplay(votes),
            onPlayerKilled: (playerId, nick, cause) => this.handlePlayerKilled(playerId, nick, cause),
            onMessage: (message, type) => this.showMessage(message, type),
            onPlayersUpdated: (players) => this.updatePlayers(players),
            onGameEnded: (winner, survivors) => this.handleGameEnded(winner, survivors),
            onConnectionChanged: (connected) => this.updateConnectionStatus(connected),
        };

        this.gameEngine = new GameEngine(this.gameId, callbacks);
        this.gameEngine.setCurrentPlayerId(this.currentPlayerId);
        await this.gameEngine.init();
    }

    /**
     * Inicializar en modo demo (sin WebSocket)
     */
    private async initDemo(): Promise<void> {
        if (this.gameId) {
            await this.loadPlayersFromAPI();
        } else {
            this.loadDemoPlayers();
        }
        
        this.renderPlayers();
        
        if (this.isCurrentPlayerAlive) {
            this.showMessage('🎮 Modo Demo - Haz clic en un jugador para votar', 'system');
        } else {
            this.showMessage('💀 Estás muerto. No puedes realizar acciones.', 'system');
        }
    }

    // ==================== OBTENER IDs ====================

    private getGameIdFromUrl(): number {
        const params = new URLSearchParams(window.location.search);
        return parseInt(params.get('id') || '0');
    }

    private getCurrentPlayerIdFromStorage(): number {
        // Obtener usuario de sessionStorage (donde lo guarda el login)
        const userStr = sessionStorage.getItem('user');
        if (userStr) {
            try {
                const user = JSON.parse(userStr);
                return user.id || 1;
            } catch {
                return 1;
            }
        }
        return 1; // Default para demo
    }

    private getAuthToken(): string | null {
        return sessionStorage.getItem('token');
    }

    // ==================== CARGAR JUGADORES ====================

    private async loadPlayersFromAPI(): Promise<void> {
        try {
            const token = this.getAuthToken();
            const response = await fetch(`${API_URL}/game/${this.gameId}/players`, {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });

            const result = await response.json();
            
            if (result.success) {
                const data = result.data;
                this.currentPlayerId = data.currentPlayerId || 1;
                this.isCurrentPlayerAlive = data.isCurrentPlayerAlive ?? true;
                this.players = data.players.map((p: any) => ({
                    ...p,
                    votes: 0
                }));
                
                const titleEl = document.querySelector('.section-title');
                if (titleEl) {
                    titleEl.textContent = `Jugadores - ${data.gameName}`;
                }
            } else {
                this.showMessage('Error al cargar jugadores', 'system');
                this.loadDemoPlayers();
            }
        } catch (error) {
            console.error('Error cargando jugadores:', error);
            this.showMessage('Error de conexión. Cargando demo...', 'system');
            this.loadDemoPlayers();
        }
    }

    private loadDemoPlayers(): void {
        this.currentPlayerId = 1;
        this.isCurrentPlayerAlive = true;
        this.players = [
            { id: 1, lobbyId: 1, nick: 'Tú (Aldeano)', isAlive: true, votes: 0, character: 'Aldeano' },
            { id: 2, lobbyId: 2, nick: 'LoboFeroz', isAlive: true, votes: 0, character: 'Hombre Lobo', isWolf: true },
            { id: 3, lobbyId: 3, nick: 'Aldeano123', isAlive: true, votes: 0, character: 'Aldeano' },
            { id: 4, lobbyId: 4, nick: 'VidenteMagica', isAlive: true, votes: 0, character: 'Vidente' },
            { id: 5, lobbyId: 5, nick: 'CazadorPro', isAlive: true, votes: 0, character: 'Cazador' },
            { id: 6, lobbyId: 6, nick: 'BrujaOscura', isAlive: true, votes: 0, character: 'Bruja' },
            { id: 7, lobbyId: 7, nick: 'LoboSilencioso', isAlive: true, votes: 0, character: 'Hombre Lobo', isWolf: true },
            { id: 8, lobbyId: 8, nick: 'NiñaPequeña', isAlive: true, votes: 0, character: 'Niña' },
        ];
    }

    // ==================== RENDERIZADO ====================

    private renderPlayers(): void {
        if (!this.playersGrid) return;

        this.playersGrid.innerHTML = '';

        this.players.forEach(player => {
            const playerCard = this.createPlayerCard(player);
            this.playersGrid!.appendChild(playerCard);
        });
    }

    private createPlayerCard(player: Player): HTMLElement {
        const card = document.createElement('div');
        card.className = `player-card${!player.isAlive ? ' dead' : ''}`;
        card.dataset.playerId = player.id.toString();

        // Avatar con inicial
        const avatar = document.createElement('div');
        avatar.className = 'player-avatar';
        
        // Mostrar icono de lobo si soy lobo y el otro también
        if (player.isWolf && this.isCurrentPlayerWolf()) {
            avatar.textContent = '🐺';
            avatar.title = 'Hombre Lobo';
        } else {
            avatar.textContent = player.nick.charAt(0).toUpperCase();
        }

        // Nick del jugador
        const nick = document.createElement('div');
        nick.className = 'player-nick';
        nick.textContent = player.nick + (player.id === this.currentPlayerId ? ' (Tú)' : '');
        nick.title = player.nick;

        // Mostrar votos
        const votesDisplay = document.createElement('div');
        votesDisplay.className = 'player-votes';
        votesDisplay.id = `votes-${player.id}`;
        votesDisplay.textContent = player.votes && player.votes > 0 ? `🗳️ ${player.votes}` : '';

        // Estado (vivo/muerto)
        const status = document.createElement('div');
        status.className = 'player-status';
        status.textContent = player.isAlive ? '🟢' : '💀';

        card.appendChild(avatar);
        card.appendChild(nick);
        card.appendChild(votesDisplay);
        card.appendChild(status);

        // Solo permitir votar si cumple condiciones
        if (this.canVoteFor(player)) {
            card.classList.add('votable');
            card.addEventListener('click', () => this.onPlayerClick(player));
        }

        return card;
    }

    /**
     * Verificar si el jugador actual es lobo
     */
    private isCurrentPlayerWolf(): boolean {
        if (this.gameEngine) {
            return this.gameEngine.isWolf();
        }
        const me = this.players.find(p => p.id === this.currentPlayerId);
        return me?.character === 'Hombre Lobo';
    }

    /**
     * Verificar si puedo votar a un jugador
     */
    private canVoteFor(player: Player): boolean {
        if (!this.isCurrentPlayerAlive) return false;
        if (player.id === this.currentPlayerId) return false;
        if (!player.isAlive) return false;
        if (this.hasVoted) return false;

        if (this.gameEngine) {
            return this.gameEngine.canVoteFor(player.id);
        }

        return true;
    }

    // ==================== MANEJO DE VOTOS (DEMO) ====================

    private onPlayerClick(player: Player): void {
        // Modo WebSocket
        if (this.gameEngine) {
            this.gameEngine.vote(player.id);
            return;
        }

        // Modo demo
        if (!this.isCurrentPlayerAlive) {
            this.showMessage('💀 Estás muerto. No puedes votar.', 'system');
            return;
        }

        if (this.hasVoted || this.votingInProgress || !player.isAlive) {
            return;
        }

        this.hasVoted = true;
        player.votes = (player.votes || 0) + 1;
        this.showMessage(`✋ Has votado por ${player.nick}`, 'action');
        this.renderPlayers();

        setTimeout(() => this.simulateOtherVotes(), 1000);
    }

    private simulateOtherVotes(): void {
        this.votingInProgress = true;
        const alivePlayers = this.players.filter(p => p.isAlive);
        
        alivePlayers.forEach(voter => {
            if (voter.id === this.currentPlayerId) return;

            let possibleTargets = alivePlayers.filter(p => p.id !== voter.id);
            if (voter.isWolf) {
                possibleTargets = possibleTargets.filter(p => !p.isWolf);
            }

            if (possibleTargets.length > 0) {
                const randomTarget = possibleTargets[Math.floor(Math.random() * possibleTargets.length)];
                randomTarget.votes = (randomTarget.votes || 0) + 1;
            }
        });

        this.renderPlayers();
        this.showMessage('🗳️ Todos han votado. Contando votos...', 'system');
        
        setTimeout(() => this.resolveVoting(), 2000);
    }

    private async resolveVoting(): Promise<void> {
        const alivePlayers = this.players.filter(p => p.isAlive);
        
        let maxVotes = 0;
        let mostVoted: Player | undefined;

        alivePlayers.forEach(player => {
            if ((player.votes || 0) > maxVotes) {
                maxVotes = player.votes || 0;
                mostVoted = player;
            }
        });

        if (mostVoted && maxVotes > 0) {
            mostVoted.isAlive = false;
            
            const emoji = mostVoted.isWolf ? '🐺' : '👤';
            this.showMessage(`⚖️ ${mostVoted.nick} ha sido eliminado con ${maxVotes} votos`, 'system');
            this.showMessage(`${emoji} Era: ${mostVoted.character || 'Aldeano'}`, 'system');
            
            if (this.gameId && mostVoted.lobbyId) {
                await this.killPlayerOnServer(mostVoted.lobbyId);
            }

            if (mostVoted.id === this.currentPlayerId) {
                this.isCurrentPlayerAlive = false;
                this.showMessage('💀 ¡Has sido eliminado!', 'system');
            }

            this.checkWinCondition();
        }

        this.players.forEach(p => p.votes = 0);
        this.hasVoted = false;
        this.votingInProgress = false;
        
        this.renderPlayers();

        if (this.isCurrentPlayerAlive) {
            setTimeout(() => {
                this.showMessage('🗳️ Nueva ronda. Haz clic en un jugador para votar', 'system');
            }, 2000);
        }
    }

    private checkWinCondition(): void {
        const alivePlayers = this.players.filter(p => p.isAlive);
        const aliveWolves = alivePlayers.filter(p => p.isWolf);
        const aliveVillagers = alivePlayers.filter(p => !p.isWolf);

        if (aliveWolves.length === 0) {
            this.showMessage('🏘️ ¡Los Aldeanos han ganado!', 'system');
            this.showMessage('🎉 Todos los lobos han sido eliminados', 'system');
        } else if (aliveWolves.length >= aliveVillagers.length) {
            this.showMessage('🐺 ¡Los Lobos han ganado!', 'system');
            this.showMessage('💀 Los lobos superan a los aldeanos', 'system');
        }
    }

    private async killPlayerOnServer(lobbyId: number): Promise<void> {
        try {
            const token = this.getAuthToken();
            await fetch(`${API_URL}/game/${this.gameId}/kill`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ player_id: lobbyId })
            });
        } catch (error) {
            console.error('Error al guardar muerte:', error);
        }
    }

    // ==================== CALLBACKS DEL GAME ENGINE ====================

    private handlePhaseChanged(phase: GamePhase, message: string, _duration: number): void {
        if (this.phaseDisplay) {
            const phaseNames: Record<GamePhase, string> = {
                waiting: '⏳ Esperando',
                cupido: '💘 Cupido',
                thief: '🎭 Ladrón',
                protector: '🛡️ Protector',
                seer: '🔮 Vidente',
                wolves: '🐺 Lobos',
                witch: '🧙‍♀️ Bruja',
                day: '☀️ Día',
            };
            this.phaseDisplay.textContent = phaseNames[phase] || phase;
        }
        
        this.showMessage(message, 'system');
    }

    private handleVotingStarted(_votingType: VotingType, canVote: boolean, _targets: number[]): void {
        if (canVote) {
            this.showMessage('🗳️ ¡Es tu turno de votar!', 'system');
        } else {
            this.showMessage('⏳ Esperando votación...', 'system');
        }
        this.renderPlayers();
    }

    private updateVotesDisplay(votes: Record<number, number>): void {
        Object.entries(votes).forEach(([playerId, voteCount]) => {
            const votesEl = document.getElementById(`votes-${playerId}`);
            if (votesEl) {
                votesEl.textContent = voteCount > 0 ? `🗳️ ${voteCount}` : '';
            }
        });
    }

    private handlePlayerKilled(playerId: number, _nick: string, _cause: string): void {
        const player = this.players.find(p => p.id === playerId);
        if (player) {
            player.isAlive = false;
        }
        this.renderPlayers();
    }

    private updatePlayers(players: Player[]): void {
        this.players = players;
        this.renderPlayers();
    }

    private handleGameEnded(winner: string, survivors: any[]): void {
        const winnerEmoji = winner === 'wolves' ? '🐺' : '🏘️';
        const winnerText = winner === 'wolves' ? 'Los Lobos' : 'Los Aldeanos';
        
        this.showMessage(`${winnerEmoji} ¡${winnerText} han ganado!`, 'system');
        this.showMessage('📋 Supervivientes:', 'system');
        survivors.forEach(s => {
            this.showMessage(`  - ${s.nick} (${s.role})`, 'system');
        });
    }

    private updateConnectionStatus(connected: boolean): void {
        if (this.connectionStatus) {
            this.connectionStatus.textContent = connected ? '🟢 Online' : '🔴 Offline';
            this.connectionStatus.className = connected ? 'status-online' : 'status-offline';
        }
    }

    private updateTimer(seconds: number): void {
        if (this.timerDisplay) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            this.timerDisplay.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    // ==================== MENSAJES ====================

    private showMessage(message: string, type: string = 'system'): void {
        if (!this.chatContainer) return;

        const messageEl = document.createElement('p');
        messageEl.className = `game-message message-${type}`;
        
        const timestamp = new Date().toLocaleTimeString('es-ES', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });
        
        messageEl.innerHTML = `<span class="msg-time">[${timestamp}]</span> ${message}`;
        
        const placeholder = this.chatContainer.querySelector('.chat-placeholder');
        if (placeholder) {
            placeholder.remove();
        }
        
        this.chatContainer.appendChild(messageEl);
        this.chatContainer.scrollTop = this.chatContainer.scrollHeight;
    }

    // ==================== CLEANUP ====================

    public destroy(): void {
        if (this.gameEngine) {
            this.gameEngine.destroy();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new GamePage();
});

export default GamePage;
