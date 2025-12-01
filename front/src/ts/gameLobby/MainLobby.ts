import { getGameInfo, leaveGame, startGame, updateGameDetails } from './CrudLobby.ts'
import { requireAuth, getUser, getToken } from '../auth.ts'
import routes from '../routes.ts'
import Pusher from 'pusher-js';
import { 
    loadConfig,
    getReverbConfig,
    getApiConfig
} from '../chatWebsocket/connection/config';

let gameData: any = null
let currentGameId: number | null = null
let currentUserId: number | null = null
let startButton: HTMLButtonElement | null = null
let isStartingGame = false
let editButton: HTMLButtonElement | null = null
let editModal: HTMLElement | null = null
let editNameInput: HTMLInputElement | null = null
let editMaxPlayersInput: HTMLInputElement | null = null
let confirmEditButton: HTMLButtonElement | null = null
let cancelEditButton: HTMLButtonElement | null = null
let isUpdatingGame = false

// Variables del juego
let gameMode: 'lobby' | 'playing' = 'lobby'
let hasVoted = false
let votingInProgress = false
let votingType: 'wolves' | 'village' | null = null
let eligibleVoters: number[] = []
let eligibleTargets: number[] = []
let votes: Map<number, number[]> = new Map()
let currentPhase: string = 'lobby'
let timerInterval: number | null = null

const resolveCurrentUserId = async (): Promise<number | null> => {
    const storedUser = getUser()
    if (storedUser?.id) {
        return storedUser.id
    }

    const token = getToken()
    if (!token) {
        return null
    }

    try {
        const response = await fetch(routes.profileUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        })

        if (response.status === 401) {
            window.location.href = routes.login
            return null
        }

        if (!response.ok) {
            return null
        }

        const data = await response.json()
        if (data.success && data.data && typeof data.data.id === 'number') {
            return data.data.id
        }
    } catch (error) {
        console.error('Error al obtener el usuario actual:', error)
    }

    return null
}

// Obtiene el ID de la partida desde la URL
const getGameIdFromUrl = (): number | null => {
    const urlParams = new URLSearchParams(window.location.search)
    const gameId = urlParams.get('gameId')
    return gameId ? parseInt(gameId, 10) : null
}

// Carga la info de la partida
const loadGameInfo = async (gameId: number) => {
    try {
        const response = await getGameInfo(gameId)
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return
            }
            const text = await response.text()
            try {
                const data = JSON.parse(text)
                alert(data.message || 'Error al cargar la partida')
            } catch {
                alert(`Error ${response.status}: No se pudo cargar la partida`)
            }
            return
        }
        
        const data = await response.json()
        
        if (data.success && data.data) {
            gameData = data.data
            updateLobbyInfo()
            await renderCards()
        } else {
            alert(data.message || 'Error al cargar la partida')
        }
    } catch (error) {
        console.error('Error al cargar la partida:', error)
        alert('Error de conexión al cargar la partida')
    }
}

// Actualiza titulo e instrucciones del lobby
const updateLobbyInfo = () => {
    if (!gameData) return

    const lobbyTitle = document.getElementById('lobbyTitle')
    const lobbyInstruction = document.getElementById('lobbyInstruction')

    if (lobbyTitle) {
        lobbyTitle.textContent = gameData.name || 'Lobby de Partida'
    }

    if (lobbyInstruction) {
        const currentPlayers = gameData.current_players || 0
        const maxPlayers = gameData.max_players || 30
        const status = gameData.status?.name || 'desconocido'
        
        let instructionText = `Jugadores: ${currentPlayers}/${maxPlayers}`
        
        // Agregar información del estado según corresponda
        if (status === 'en_espera') {
            instructionText += ' - Esperando jugadores...'
        } else if (status === 'en_curso') {
            instructionText += ' - Partida en curso'
        } else if (status === 'finalizada') {
            instructionText += ' - Partida finalizada'
        }
        
        lobbyInstruction.textContent = instructionText
    }

    updateStartButtonState()
}

const getStatusName = (status: any): string => {
    if (!status) {
        return ''
    }
    if (typeof status === 'string') {
        return status
    }
    if (typeof status.name === 'string') {
        return status.name
    }
    if (typeof status.code === 'string') {
        return status.code
    }
    return ''
}

const updateStartButtonState = () => {
    if (!startButton) {
        startButton = document.getElementById('startGameBtn') as HTMLButtonElement | null
    }
    if (!editButton) {
        editButton = document.getElementById('editGameBtn') as HTMLButtonElement | null
    }

    const hideHostControls = () => {
        if (startButton) {
            startButton.style.display = 'none'
            startButton.disabled = true
            startButton.textContent = 'Empezar partida'
            startButton.removeAttribute('title')
        }
        if (editButton) {
            editButton.style.display = 'none'
            editButton.disabled = true
            editButton.textContent = 'Editar partida'
            editButton.removeAttribute('title')
        }
    }

    if (!startButton) {
        return
    }

    if (!gameData) {
        hideHostControls()
        return
    }

    const hostId = gameData.host?.id ?? null
    const isHost = currentUserId !== null && hostId === currentUserId

    if (!isHost) {
        hideHostControls()
        return
    }

    const statusName = getStatusName(gameData.status).toLowerCase()
    const gameAlreadyStarted = statusName === 'en_progreso' || statusName === 'en_curso' || statusName === 'finalizada'
    const playersArray = Array.isArray(gameData.players) ? gameData.players : []
    const currentPlayers = typeof gameData.current_players === 'number'
        ? gameData.current_players
        : playersArray.length
    const maxPlayers = typeof gameData.max_players === 'number'
        ? gameData.max_players
        : playersArray.length
    const canStart = !gameAlreadyStarted && maxPlayers > 0
    const canEdit = !gameAlreadyStarted

    if (editButton) {
        editButton.style.display = 'block'
        if (isUpdatingGame) {
            editButton.disabled = true
            editButton.textContent = 'Guardando...'
            editButton.removeAttribute('title')
        } else {
            editButton.disabled = !canEdit
            editButton.textContent = 'Editar partida'

            if (!canEdit) {
                editButton.title = 'No se puede editar una partida en curso o finalizada'
            } else {
                editButton.removeAttribute('title')
            }
        }
    }

    startButton.style.display = 'block'
    if (isStartingGame) {
        startButton.disabled = true
        startButton.textContent = 'Iniciando...'
        startButton.removeAttribute('title')
        return
    }

    startButton.disabled = !canStart
    startButton.textContent = 'Empezar partida'

    if (!canStart) {
        startButton.title = `Jugadores ${currentPlayers}/${maxPlayers}`
    } else {
        startButton.removeAttribute('title')
    }
}

const bindEditButton = (gameId: number) => {
    editButton = document.getElementById('editGameBtn') as HTMLButtonElement | null
    editModal = document.getElementById('editGameModal') as HTMLElement | null
    editNameInput = document.getElementById('editGameNameLobby') as HTMLInputElement | null
    editMaxPlayersInput = document.getElementById('editGameMaxPlayersLobby') as HTMLInputElement | null
    confirmEditButton = document.getElementById('confirmEditGameBtn') as HTMLButtonElement | null
    cancelEditButton = document.getElementById('cancelEditGameBtn') as HTMLButtonElement | null

    if (!editButton || !editModal || !editNameInput || !editMaxPlayersInput || !confirmEditButton || !cancelEditButton) {
        return
    }

    const closeModal = () => {
        if (!editModal) {
            return
        }
        editModal.style.display = 'none'
        editModal.setAttribute('aria-hidden', 'true')
    }

    const openModal = () => {
        if (!gameData || !editModal || !editNameInput || !editMaxPlayersInput || !confirmEditButton) {
            alert('No se puede editar la partida en este momento')
            return
        }

        editNameInput.value = gameData.name || ''
        const maxPlayersValue = typeof gameData.max_players === 'number' ? gameData.max_players : 15
        editMaxPlayersInput.value = maxPlayersValue.toString()
        confirmEditButton.disabled = false
        editModal.style.display = 'flex'
        editModal.setAttribute('aria-hidden', 'false')
    }

    const handleCancel = () => {
        if (isUpdatingGame) {
            return
        }
        closeModal()
    }

    const handleOverlayClick = (event: MouseEvent) => {
        if (editModal && event.target === editModal && !isUpdatingGame) {
            closeModal()
        }
    }

    const handleConfirm = async () => {
        if (!gameData || currentGameId === null || !editNameInput || !editMaxPlayersInput || !confirmEditButton) {
            return
        }

        const name = editNameInput.value.trim()
        const maxPlayers = parseInt(editMaxPlayersInput.value, 10)

        if (!name) {
            alert('El nombre es obligatorio')
            return
        }

        if (isNaN(maxPlayers) || maxPlayers < 15 || maxPlayers > 30) {
            alert('El número máximo de jugadores debe estar entre 15 y 30')
            return
        }

        isUpdatingGame = true
        confirmEditButton.disabled = true
        updateStartButtonState()

        try {
            const response = await updateGameDetails(gameId, {
                name,
                max_players: maxPlayers
            })

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = routes.login
                    return
                }

                const text = await response.text()
                try {
                    const data = JSON.parse(text)
                    if (data.errors) {
                        const errorMessages = Object.values(data.errors).flat()
                        alert(errorMessages.join(', '))
                    } else {
                        alert(data.message || 'Error al actualizar la partida')
                    }
                } catch {
                    alert(`Error ${response.status}: No se pudo actualizar la partida`)
                }
                return
            }

            const data = await response.json()

            if (data.success && data.data) {
                gameData = {
                    ...gameData,
                    name: data.data.name ?? name,
                    max_players: data.data.max_players ?? maxPlayers,
                    status: data.data.status ?? gameData.status
                }

                if (data.data.players) {
                    gameData.players = data.data.players
                }

                isUpdatingGame = false
                if (confirmEditButton) {
                    confirmEditButton.disabled = false
                }
                closeModal()
                updateLobbyInfo()

                // La actualización se refleja automáticamente vía websocket, no se muestra alerta
            } else {
                alert(data.message || 'Error al actualizar la partida')
            }
        } catch (error) {
            console.error('Error al actualizar la partida:', error)
            alert('Error de conexión al actualizar la partida')
        } finally {
            if (isUpdatingGame) {
                isUpdatingGame = false
            }
            if (confirmEditButton) {
                confirmEditButton.disabled = false
            }

            updateStartButtonState()
        }
    }

    editButton.addEventListener('click', openModal)
    cancelEditButton.addEventListener('click', handleCancel)
    confirmEditButton.addEventListener('click', handleConfirm)
    editModal.addEventListener('click', handleOverlayClick)
}

const bindStartButton = (gameId: number) => {
    startButton = document.getElementById('startGameBtn') as HTMLButtonElement | null
    if (!startButton) {
        return
    }

    const handleClick = async () => {
        if (!startButton || startButton.disabled || isStartingGame) {
            return
        }

        if (!requireAuth()) {
            return
        }

        isStartingGame = true
        updateStartButtonState()

        try {
            const response = await startGame(gameId)

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = routes.login
                    return
                }

                const text = await response.text()
                try {
                    const data = JSON.parse(text)
                    alert(data.message || 'Error al iniciar la partida')
                } catch {
                    alert(`Error ${response.status}: No se pudo iniciar la partida`)
                }
                return
            }

            const data = await response.json()
            if (data.success && data.data) {
                gameData = {
                    ...gameData,
                    ...data.data,
                    status: data.data.status ?? gameData?.status
                }

                if (data.data.players) {
                    gameData.players = data.data.players
                }

                if (typeof data.data.current_players === 'number') {
                    gameData.current_players = data.data.current_players
                }

                updateLobbyInfo()
                alert(data.message || 'La partida ha comenzado')
            } else {
                alert(data.message || 'Error al iniciar la partida')
            }
        } catch (error) {
            console.error('Error al iniciar la partida:', error)
            alert('Error de conexión al iniciar la partida')
        } finally {
            isStartingGame = false
            renderCards()
            updateStartButtonState()

        }
    }

    startButton.addEventListener('click', handleClick)
}

const bindAbandonButton = (gameId: number) => {
    const abandonBtn = document.getElementById('abandonBtn') as HTMLButtonElement | null
    if (!abandonBtn) {
        return
    }

    let isProcessing = false

    const handleClick = async () => {
        if (isProcessing) {
            return
        }

        if (!requireAuth()) {
            return
        }

        const confirmed = confirm('¿Seguro que quieres abandonar la partida?')
        if (!confirmed) {
            return
        }

        isProcessing = true
        abandonBtn.disabled = true

        try {
            const response = await leaveGame(gameId)

            if (!response.ok) {
                if (response.status === 401) {
                    window.location.href = routes.login
                    return
                }

                const text = await response.text()
                try {
                    const data = JSON.parse(text)
                    alert(data.message || 'Error al abandonar la partida')
                } catch {
                    alert(`Error ${response.status}: No se pudo abandonar la partida`)
                }
                return
            }

            const data = await response.json()
            if (data.success) {
                window.location.href = routes.findGame
                return
            }

            alert(data.message || 'No se pudo abandonar la partida')
        } catch (error) {
            console.error('Error al abandonar la partida:', error)
            alert('Error de conexión al abandonar la partida')
        } finally {
            if (isProcessing) {
                isProcessing = false
                abandonBtn.disabled = false
            }
        }
    }

    abandonBtn.addEventListener('click', handleClick)
}

// Renderiza las cartas de jugadores
const renderCards = () => {
    const cardsContainer = document.getElementById('cardsContainer')
    if (!cardsContainer || !gameData) return

    cardsContainer.innerHTML = ''

    const maxPlayers = gameData.max_players || 30
    const players = (gameData.players || []).sort(() => Math.random() - 0.5)

    if (Array.isArray(players)) {
        gameData.current_players = players.length
    }
    
    // Crear un array de jugadores indexado por posición
    const playersByPosition: (any | null)[] = new Array(maxPlayers).fill(null)
    
    // Asignar jugadores a las primeras posiciones disponibles
    players.forEach((player: any, index: number) => {
        if (index < maxPlayers) {
            playersByPosition[index] = player
        }
    })

    // Verificar si la partida está en curso
    const statusName = getStatusName(gameData.status).toLowerCase()
    const isGameInProgress = statusName === 'en_progreso' || statusName === 'en_curso'

    // Renderizar cartas hasta max_players
    for (let i = 0; i < maxPlayers; i++) {
        const player = playersByPosition[i]
        const isEnabled = !!player
        //const isHost = isEnabled && player && hostId !== null && player.id === hostId

        // Crear tarjeta rectangular
        const playerCard = document.createElement('div')
        playerCard.classList.add('player-card')
        
        if (!isEnabled) {
            playerCard.classList.add('disabled')
        }
        //if (isHost) {
        //    playerCard.classList.add('host-card')
        //}

        // Si la partida está en curso y hay votación, añadir clase votable
        if (isEnabled && isGameInProgress && votingInProgress && player) {
            const isAlive = player.is_alive !== false
            const isNotSelf = player.id !== currentUserId
            
            // Verificar si el usuario actual puede votar y si el jugador es un objetivo válido
            const canUserVote = eligibleVoters.length === 0 || eligibleVoters.includes(currentUserId!)
            const isValidTarget = eligibleTargets.length === 0 || eligibleTargets.includes(player.id)
            
            if (isAlive && isNotSelf && !hasVoted && canUserVote && isValidTarget) {
                playerCard.classList.add('votable')
                playerCard.addEventListener('click', () => handleVoteClick(player.id, player.nickname))
            }
            
            if (!isAlive) {
                playerCard.classList.add('dead')
            }
        }

        // Avatar del jugador
        const avatar = document.createElement('div')
        avatar.classList.add('player-avatar')

        const currentPlayer = players.find(p  => p.id === currentUserId)
        if (player != null) {
            if (currentPlayer.character === 3 && player && player.character === 3) {
                playerCard.classList.add('lobo')
            } else if (currentPlayer.id == player.id) {
                switch (player.character) {
                    case 2: playerCard.classList.add('aldeano'); break;
                    case 4: playerCard.classList.add('cupido'); break;
                    case 5: playerCard.classList.add('ladron'); break;
                    case 6: playerCard.classList.add('protector'); break;
                    case 7: playerCard.classList.add('bruja'); break;
                    case 8: playerCard.classList.add('vidente'); break;
                    case 9: playerCard.classList.add('niña'); break;
                    default:
                }
            }
        }

        if (isEnabled && player) {
            if (player.profile_image) {
                const avatarImg = document.createElement('img')
                avatarImg.src = player.profile_image
                avatarImg.alt = player.nickname
                avatar.appendChild(avatarImg)
            } else {
                avatar.textContent = player.nickname ? player.nickname.charAt(0).toUpperCase() : '?'
            }
        } else {
            avatar.textContent = '?'
        }
        playerCard.appendChild(avatar)

        // Nickname del usuario
        if (isEnabled && player && player.nickname) {
            const nick = document.createElement('div')
            nick.classList.add('player-nick')
            nick.textContent = player.nickname
            playerCard.appendChild(nick)
        } else {
            const label = document.createElement('div')
            label.classList.add('player-label')
            label.textContent = `Vacante ${i + 1}`
            playerCard.appendChild(label)
        }

        // Mostrar votos si hay votación en curso
        if (isGameInProgress && votingInProgress && isEnabled && player) {
            const playerVotes = votes.get(player.id)
            const votesDiv = document.createElement('div')
            votesDiv.classList.add('player-votes')
            votesDiv.id = `votes-${player.id}`
            if (playerVotes && playerVotes.length > 0) {
                votesDiv.textContent = `🗳️ ${playerVotes.length}`
            }
            playerCard.appendChild(votesDiv)
        }

        // Mostrar estado si está muerto
        if (isEnabled && player && player.is_alive === false) {
            const statusDiv = document.createElement('div')
            statusDiv.classList.add('player-status')
            statusDiv.textContent = '💀 Muerto'
            playerCard.appendChild(statusDiv)
        }

        cardsContainer.appendChild(playerCard)
    }

    updateStartButtonState()
}

// Click en votar
const handleVoteClick = async (targetId: number, targetNickname: string) => {
    if (hasVoted || !votingInProgress || !currentGameId) {
        return
    }

    const confirmed = confirm(`¿Votar por ${targetNickname}?`)
    if (!confirmed) return

    hasVoted = true
    
    try {
        const token = getToken()
        const response = await fetch(`${routes.gameplayUrl}/${currentGameId}/vote`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({ target_id: targetId })
        })

        if (response.ok) {
            await response.json()
            addGameMessage(`Has votado por ${targetNickname}`, 'action')
            
            if (!votes.has(targetId)) {
                votes.set(targetId, [])
            }
            votes.get(targetId)?.push(currentUserId!)
            renderCards()
        } else {
            hasVoted = false
            addGameMessage('Error al enviar el voto', 'system')
        }
    } catch (error) {
        hasVoted = false
        console.error('Error al votar:', error)
        addGameMessage('Error de conexión al votar', 'system')
    }
}

// Añade mensaje al chat
const addGameMessage = (message: string, type: 'system' | 'action' | 'chat' | 'wolves_chat' = 'system') => {
    const chatMessages = document.getElementById('chatMessages')
    if (!chatMessages) return

    const placeholder = chatMessages.querySelector('.chat-placeholder')
    if (placeholder) {
        placeholder.remove()
    }

    const messageDiv = document.createElement('div')
    messageDiv.classList.add('game-message', `message-${type}`)
    
    const time = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
    messageDiv.innerHTML = `<span class="msg-time">[${time}]</span> ${message}`
    
    chatMessages.appendChild(messageDiv)
    chatMessages.scrollTop = chatMessages.scrollHeight
}

// Actualiza la barra de estado
const updateGameStatusBar = () => {
    const phaseDisplay = document.getElementById('phaseDisplay')
    const connectionStatus = document.getElementById('connectionStatus')
    const gameStatusBar = document.querySelector('.game-status-bar') as HTMLElement

    const statusName = getStatusName(gameData?.status).toLowerCase()
    const isGameInProgress = statusName === 'en_progreso' || statusName === 'en_curso'

    if (gameStatusBar) {
        gameStatusBar.style.display = isGameInProgress ? 'flex' : 'none'
    }

    if (!isGameInProgress) return

    if (phaseDisplay) {
        const phaseNames: Record<string, string> = {
            'lobby': '🏠 Lobby',
            'cupido': '💘 Cupido',
            'thief': '🦝 Ladrón',
            'protector': '🛡️ Protector',
            'seer': '🔮 Vidente',
            'wolves': '🐺 Lobos',
            'witch': '🧙‍♀️ Bruja',
            'day': '☀️ Día'
        }
        phaseDisplay.textContent = phaseNames[currentPhase] || currentPhase
    }

    if (connectionStatus) {
        connectionStatus.textContent = 'Conectado'
        connectionStatus.className = 'status-value status-online'
    }
}

// Inicia el timer
const startPhaseTimer = (duration: number) => {
    stopPhaseTimer()
    
    let remainingSeconds = duration
    updateTimerDisplay(remainingSeconds)

    timerInterval = window.setInterval(() => {
        remainingSeconds--
        updateTimerDisplay(remainingSeconds)
        
        if (remainingSeconds <= 0) {
            stopPhaseTimer()
        }
    }, 1000)
}

// Para el timer
const stopPhaseTimer = () => {
    if (timerInterval) {
        clearInterval(timerInterval)
        timerInterval = null
    }
}

// Actualiza el timer en pantalla
const updateTimerDisplay = (seconds: number) => {
    const timerDisplay = document.getElementById('timerDisplay')
    if (timerDisplay) {
        const minutes = Math.floor(seconds / 60)
        const secs = seconds % 60
        timerDisplay.textContent = `${minutes}:${secs.toString().padStart(2, '0')}`
    }
}

// Cambio de fase
const handlePhaseChange = (phase: string, message: string, duration: number) => {
    currentPhase = phase
    updateGameStatusBar()
    addGameMessage(message, 'system')
    
    if (duration > 0) {
        startPhaseTimer(duration)
    }

    if (phase === 'day') {
        votingInProgress = true
        hasVoted = false
        votes.clear()
        renderCards()
    } else {
        votingInProgress = false
        votingType = null
        eligibleVoters = []
        eligibleTargets = []
    }
}

// Procesa votos del websocket
const processVotes = (votesData: Record<string, number[]>) => {
    votes.clear()
    for (const [targetId, voterIds] of Object.entries(votesData)) {
        votes.set(parseInt(targetId), voterIds)
    }
    renderCards()
}

// Verifica si la partida esta en curso
const checkGameMode = () => {
    const statusName = getStatusName(gameData?.status).toLowerCase()
    const isGameInProgress = statusName === 'en_progreso' || statusName === 'en_curso'

    if (isGameInProgress && gameMode !== 'playing') {
        gameMode = 'playing'
        updateGameStatusBar()
        addGameMessage('🎮 ¡La partida ha comenzado!', 'system')
    } else if (!isGameInProgress && gameMode === 'playing') {
        gameMode = 'lobby'
        stopPhaseTimer()
    }
}

// ======== Variables para el chat ========
let pusher: Pusher | null = null
let chatChannels: any[] = []
let chatInitialized = false

// ======== Variables para actualizaciones del lobby ========
let lobbyChannel: any = null
let lobbyUpdatesInitialized = false

// Renderiza mensaje en el chat
const renderChatMessage = (data: any, container: HTMLElement) => {
    const messageDiv = document.createElement('div')
    messageDiv.classList.add('chat-message')

    const userName = data.user_name || data.user_nickname || 'Usuario'

    let typePrefix = ''
    if (data.type === 'private') {
        typePrefix = '🔒 '
    } else if (data.type === 'group') {
        typePrefix = '👥 '
    }

    messageDiv.textContent = `${typePrefix}${userName}: ${data.message}`
    container.appendChild(messageDiv)
    container.scrollTop = container.scrollHeight
}

// Carga historial del chat
const loadChatHistory = async (gameId: number, container: HTMLElement) => {
    const { loadChatHistory: loadHistory } = await import('../chatWebsocket/chatArchive')
    
    const messages = await loadHistory(gameId)
    
    if (messages && messages.length > 0) {
        // Limpiar mensajes existentes
        container.innerHTML = ''
        
        // Renderizar mensajes del historial
        messages.forEach((msg) => {
            renderChatMessage(msg, container)
        })
    }
}

// Inicializa el chat
const initChat = async (gameId: number) => {
    if (chatInitialized) return

    const chatMessagesContainer = document.getElementById('chatMessages')
    const chatInput = document.getElementById('chatInput') as HTMLInputElement
    const sendMessageBtn = document.getElementById('sendMessageBtn') as HTMLButtonElement

    if (!chatMessagesContainer || !chatInput || !sendMessageBtn) {
        console.warn('Elementos del chat no encontrados')
        return
    }

    try {
        // Cargar configuración
        await loadConfig()
        const reverbConfig = getReverbConfig()
        const apiConfig = getApiConfig()

        // Determinar el host WebSocket
        const wsHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
            ? 'localhost'
            : reverbConfig.host === '127.0.0.1'
            ? 'localhost'
            : reverbConfig.host

        const token = getToken()
        if (!token) {
            console.error('No hay token de autenticación para el chat')
            return
        }

        // Configurar Pusher
        pusher = new Pusher(reverbConfig.appKey, {
            wsHost: wsHost,
            wsPort: reverbConfig.port,
            forceTLS: false,
            enabledTransports: ['ws'],
            cluster: reverbConfig.cluster,
            disableStats: true,
            authEndpoint: `http://${apiConfig.host}:${apiConfig.port}/broadcasting/auth`,
            auth: {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            }
        })

        // Suscribirse a canales
        const publicChannel = pusher.subscribe(`chat.game.${gameId}.public`)
        chatChannels.push(publicChannel)

        if (currentUserId) {
            const privateChannel = pusher.subscribe(`private-chat.game.${gameId}.private.${currentUserId}`)
            chatChannels.push(privateChannel)
            
            const groupChannel = pusher.subscribe(`private-chat.game.${gameId}.group.${currentUserId}`)
            chatChannels.push(groupChannel)
        }

        // Eventos de conexión
        pusher.connection.bind('connected', () => {
            console.info('✅ Chat conectado a Reverb')
            const statusIndicator = document.querySelector('.chat-status-indicator')
            if (statusIndicator) {
                statusIndicator.classList.add('online')
            }
        })

        pusher.connection.bind('error', (err: any) => {
            console.error('⚠️ Error en conexión del chat:', err)
            const statusIndicator = document.querySelector('.chat-status-indicator')
            if (statusIndicator) {
                statusIndicator.classList.remove('online')
            }
        })

        // Handler para mensajes públicos
        publicChannel.bind('message.sent', (data: any) => {
            if (data.type === 'public' && data.game_id === gameId) {
                renderChatMessage(data, chatMessagesContainer)
            }
        })

        // Handler para mensajes privados
        if (currentUserId && chatChannels.length > 1) {
            const privateChannel = chatChannels.find(c => c.name && c.name.includes(`private.${currentUserId}`))
            if (privateChannel) {
                privateChannel.bind('message.sent', (data: any) => {
                    if (data.type === 'private' && 
                        data.game_id === gameId &&
                        (data.user_id === currentUserId || data.recipient_id === currentUserId)) {
                        renderChatMessage(data, chatMessagesContainer)
                    }
                })
            }

            // Handler para mensajes de grupo
            const groupChannel = chatChannels.find(c => c.name && c.name.includes(`group.${currentUserId}`))
            if (groupChannel) {
                groupChannel.bind('message.sent', (data: any) => {
                    if (data.type === 'group' && 
                        data.game_id === gameId &&
                        (data.user_id === currentUserId || 
                         (data.recipient_ids && data.recipient_ids.includes(currentUserId)))) {
                        renderChatMessage(data, chatMessagesContainer)
                    }
                })
            }
        }

        // Cargar historial
        await loadChatHistory(gameId, chatMessagesContainer)

        // Event listener para enviar mensaje
        const sendMessage = async () => {
            const message = chatInput.value.trim()
            if (!message) return

            const apiConfig = getApiConfig()
            const url = `http://${apiConfig.host}:${apiConfig.port}/api/chat/${gameId}/send`

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ message })
                })

                if (response.ok) {
                    chatInput.value = ''
                } else {
                    const error = await response.json()
                    alert(error.message || 'Error al enviar el mensaje')
                }
            } catch (error) {
                console.error('Error al enviar mensaje:', error)
                alert('Error de conexión al enviar el mensaje')
            }
        }

        sendMessageBtn.addEventListener('click', sendMessage)
        chatInput.addEventListener('keypress', (e: KeyboardEvent) => {
            if (e.key === 'Enter') {
                sendMessage()
            }
        })

        chatInitialized = true
    } catch (error) {
        console.error('Error al inicializar el chat:', error)
    }
}

// Inicializa actualizaciones del lobby via websocket
const initLobbyUpdates = async (gameId: number) => {
    if (lobbyUpdatesInitialized) return

    try {
        // Si ya tenemos una instancia de Pusher del chat, reutilizarla
        // Si no existe, el chat la creará primero
        if (!pusher) {
            console.warn('Pusher no inicializado. El chat debe inicializarse primero.')
            return
        }

        // Suscribirse al canal del lobby
        lobbyChannel = pusher.subscribe(`game.lobby.${gameId}`)

        // Escuchar evento cuando un jugador se une
        lobbyChannel.bind('player.joined', async (data: any) => {
            console.log('Jugador se unió:', data)
            
            // Si la partida fue eliminada, no hacer nada (el usuario ya no está en la partida)
            if (data.game_deleted) {
                return
            }

            // Recargar información completa de la partida
            if (currentGameId) {
                await loadGameInfo(currentGameId)
                updateLobbyInfo()
                renderCards()
            }
        })

        // Escuchar evento cuando un jugador abandona
        lobbyChannel.bind('player.left', async (data: any) => {
            console.log('Jugador abandonó:', data)
            
            // Si la partida fue eliminada, redirigir al usuario
            if (data.game_deleted) {
                alert('La partida ha sido eliminada por no tener jugadores')
                window.location.href = routes.findGame
                return
            }

            // Recargar información completa de la partida
            if (currentGameId) {
                await loadGameInfo(currentGameId)
                updateLobbyInfo()
                renderCards()
            }
        })

        // Escuchar evento cuando se actualiza la partida (nombre, max_players, etc.)
        lobbyChannel.bind('game.updated', async (data: any) => {
            console.log('Partida actualizada:', data)
            
            // Actualizar los datos de la partida con la información recibida
            if (data.game && currentGameId === data.game_id) {
                gameData = {
                    ...gameData,
                    name: data.game.name ?? gameData?.name,
                    max_players: data.game.max_players ?? gameData?.max_players,
                    current_players: data.game.current_players ?? gameData?.current_players,
                    status: data.game.status ?? gameData?.status,
                    players: data.game.players ?? gameData?.players
                }
                
                // Actualizar la UI
                updateLobbyInfo()
                renderCards()
            }
        })

        // Escuchar evento de cambio de fase del juego
        lobbyChannel.bind('game.phase.changed', (data: any) => {
            console.log('Fase del juego cambiada:', data)
            
            if (data.game_id === currentGameId) {
                // Actualizar estado de votación
                if (data.voting_in_progress !== undefined) {
                    votingInProgress = data.voting_in_progress
                }
                
                // Manejar el cambio de fase
                handlePhaseChange(data.phase, data.phase_message || `Fase: ${data.phase}`, data.duration || 0)
            }
        })

        // Escuchar evento de inicio de votación
        lobbyChannel.bind('voting.started', (data: any) => {
            console.log('Votación iniciada:', data)
            
            if (data.gameId === currentGameId) {
                votingInProgress = true
                votingType = data.votingType
                eligibleVoters = data.eligibleVoters || []
                eligibleTargets = data.eligibleTargets || []
                hasVoted = false
                votes.clear()
                
                console.log('Votantes elegibles:', eligibleVoters)
                console.log('Objetivos elegibles:', eligibleTargets)
                
                // Re-renderizar las cartas para actualizar el estado visual
                renderCards()
            }
        })

        // Escuchar evento de voto recibido
        lobbyChannel.bind('game.vote.received', (data: any) => {
            console.log('Voto recibido:', data)
            
            if (data.game_id === currentGameId && data.votes) {
                processVotes(data.votes)
            }
        })

        // Escuchar evento de fin de votación
        lobbyChannel.bind('voting.ended', (data: any) => {
            console.log('Votación finalizada:', data)
            
            if (data.gameId === currentGameId) {
                votingInProgress = false
                votingType = null
                eligibleVoters = []
                eligibleTargets = []
                hasVoted = false
                votes.clear()
                
                // Mostrar resultado de la votación
                if (data.eliminatedPlayerNick) {
                    addGameMessage(`${data.eliminatedPlayerNick} ha sido eliminado por votación`, 'system')
                } else {
                    addGameMessage('La votación ha terminado sin eliminados', 'system')
                }
                
                // Re-renderizar las cartas
                renderCards()
            }
        })

        // Jugador eliminado
        lobbyChannel.bind('player.killed', (data: any) => {
            console.log('Jugador eliminado:', data)
            
            const causeText: Record<string, string> = {
                'wolves': 'ha sido devorado por los lobos',
                'village': 'ha sido linchado por el pueblo',
                'witch': 'ha sido envenenado por la bruja',
                'hunter': 'ha sido cazado',
                'lovers': 'ha muerto de amor'
            }
            
            const msg = causeText[data.cause] || 'ha sido eliminado'
            addGameMessage(`${data.playerNick} ${msg}`, 'system')
            
            // Marcar jugador como muerto en gameData
            if (gameData?.players) {
                const player = gameData.players.find((p: any) => p.id === data.playerId)
                if (player) player.is_alive = false
            }
            
            renderCards()
        })

        // Mensajes del juego
        lobbyChannel.bind('game.message', (data: any) => {
            console.log('Mensaje del juego:', data)
            addGameMessage(data.message, data.type || 'system')
        })

        // Fin de partida
        lobbyChannel.bind('game.ended', (data: any) => {
            console.log('Partida terminada:', data)
            
            const winnerText: Record<string, string> = {
                'wolves': 'Los lobos han ganado',
                'village': 'El pueblo ha ganado',
                'lovers': 'Los enamorados han ganado'
            }
            
            const msg = winnerText[data.winner] || 'La partida ha terminado'
            addGameMessage(msg, 'system')
            
            // Mostrar supervivientes
            if (data.survivors?.length > 0) {
                const names = data.survivors.map((s: any) => s.nickname || s.nick).join(', ')
                addGameMessage(`Supervivientes: ${names}`, 'system')
            }
            
            gameMode = 'lobby'
            stopPhaseTimer()
        })

        // Solicitud de acción
        lobbyChannel.bind('action.prompt', (data: any) => {
            console.log('Acción requerida:', data)
            
            // Solo mostrar si es para el jugador actual
            if (data.targetPlayerId !== currentUserId) return
            
            // TODO: mostrar modal de acción segun actionType
            console.log('Debes realizar una acción:', data.actionType)
        })

        // Eventos de conexión
        pusher.connection.bind('connected', () => {
            console.info('✅ Conectado a actualizaciones del lobby')
        })

        pusher.connection.bind('error', (err: any) => {
            console.error('⚠️ Error en conexión del lobby:', err)
        })

        lobbyUpdatesInitialized = true
        console.info('✅ Actualizaciones del lobby inicializadas')
    } catch (error) {
        console.error('Error al inicializar actualizaciones del lobby:', error)
    }
}

// Init
const init = async () => {
    // Verificar autenticación
    if (!requireAuth()) {
        return
    }

    // Obtener ID de partida desde URL
    const gameId = getGameIdFromUrl()
    if (!gameId) {
        alert('No se ha especificado el ID de la partida')
        window.location.href = 'findGame.html'
        return
    }

    currentGameId = gameId
    currentUserId = await resolveCurrentUserId()
    bindEditButton(gameId)
    bindStartButton(gameId)
    bindAbandonButton(gameId)
    updateStartButtonState()

    // Cargar información de la partida
    await loadGameInfo(gameId)

    // Verificar si la partida está en curso
    checkGameMode()

    // Inicializar el chat
    await initChat(gameId)

    // Inicializar actualizaciones del lobby
    await initLobbyUpdates(gameId)
}

init()

