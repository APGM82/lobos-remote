import { getGameInfo } from './CrudLobby.ts'
import { requireAuth, getToken } from '../auth.ts'

let gameData: any = null

/**
 * Obtiene el ID de la partida desde la URL
 * @returns ID de la partida o null si no se encuentra
 */
const getGameIdFromUrl = (): number | null => {
    const urlParams = new URLSearchParams(window.location.search)
    const gameId = urlParams.get('gameId')
    return gameId ? parseInt(gameId, 10) : null
}

/**
 * Carga la información de la partida desde el backend
 */
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
            renderCards()
        } else {
            alert(data.message || 'Error al cargar la partida')
        }
    } catch (error) {
        console.error('Error al cargar la partida:', error)
        alert('Error de conexión al cargar la partida')
    }
}

/**
 * Actualiza la información del lobby (título e instrucciones)
 */
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
}

/**
 * Renderiza las cartas de votación dinámicamente
 */
const renderCards = () => {
    const cardsContainer = document.getElementById('cardsContainer')
    if (!cardsContainer || !gameData) return

    cardsContainer.innerHTML = ''

    const maxPlayers = gameData.max_players || 30
    const players = gameData.players || []
    
    // Crear un array de jugadores indexado por posición
    // Los jugadores vienen como array de objetos, los mapeamos a posiciones
    const playersByPosition: (any | null)[] = new Array(maxPlayers).fill(null)
    
    // Asignar jugadores a las primeras posiciones disponibles
    players.forEach((player: any, index: number) => {
        if (index < maxPlayers) {
            playersByPosition[index] = player
        }
    })

    // Renderizar cartas hasta max_players
    for (let i = 0; i < maxPlayers; i++) {
        const player = playersByPosition[i]
        const isEnabled = !!player

        const cardWrapper = document.createElement('div')
        cardWrapper.classList.add('card-wrapper')

        const cardDiamond = document.createElement('div')
        cardDiamond.classList.add('card-diamond')
        if (!isEnabled) {
            cardDiamond.classList.add('disabled')
        }

        const cardContent = document.createElement('div')
        cardContent.classList.add('card-content')

        // Imagen del logo (siempre visible)
        const cardImage = document.createElement('img')
        cardImage.classList.add('card-image')
        cardImage.src = '../public/logo.png'
        cardImage.alt = isEnabled && player ? player.nickname : 'Jugador no asignado'
        cardContent.appendChild(cardImage)

        // Nickname del usuario (solo si está habilitado)
        if (isEnabled && player && player.nickname) {
            const cardNickname = document.createElement('div')
            cardNickname.classList.add('card-nickname')
            cardNickname.textContent = player.nickname
            cardContent.appendChild(cardNickname)
        } else {
            // Mostrar etiqueta uX si no hay jugador
            const cardLabel = document.createElement('div')
            cardLabel.classList.add('card-label')
            cardLabel.textContent = `u${i + 1}`
            cardContent.appendChild(cardLabel)
        }

        cardDiamond.appendChild(cardContent)
        cardWrapper.appendChild(cardDiamond)
        cardsContainer.appendChild(cardWrapper)
    }
}

/**
 * Inicializa la vista del lobby
 */
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

    // Cargar información de la partida
    await loadGameInfo(gameId)
}

init()

