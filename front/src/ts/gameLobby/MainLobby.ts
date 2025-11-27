import { getGameInfo, leaveGame, startGame, updateGameDetails } from './CrudLobby.ts'
import { requireAuth, getUser, getToken } from '../auth.ts'
import routes from '../routes.ts'

let gameData: any = null
let detachCardsResize: (() => void) | null = null
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
    const canStart = !gameAlreadyStarted && maxPlayers > 0 && currentPlayers >= maxPlayers
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

const adjustCardsLayout = (totalCards: number) => {
    const cardsContainer = document.getElementById('cardsContainer') as HTMLElement | null
    if (!cardsContainer || totalCards <= 0) {
        return
    }

    const styles = window.getComputedStyle(cardsContainer)
    const paddingX = parseFloat(styles.paddingLeft) + parseFloat(styles.paddingRight)
    const paddingY = parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom)
    const gapValue = styles.getPropertyValue('--card-gap')
    const gap = gapValue ? parseFloat(gapValue) : 16

    const availableWidth = cardsContainer.clientWidth - paddingX
    const availableHeight = cardsContainer.clientHeight - paddingY

    if (availableWidth <= 0 || availableHeight <= 0) {
        return
    }

    let columns = Math.ceil(Math.sqrt(totalCards * (availableWidth / availableHeight)))
    columns = Math.max(1, Math.min(columns, totalCards))
    let rows = Math.ceil(totalCards / columns)

    const calcCardSize = () => {
        const widthSpace = availableWidth - gap * (columns - 1)
        const heightSpace = availableHeight - gap * (rows - 1)

        if (widthSpace <= 0 || heightSpace <= 0) {
            return 0
        }

        return Math.min(widthSpace / columns, heightSpace / rows)
    }

    let cardSize = calcCardSize()
    const MAX_CARD_SIZE = 130
    const MIN_CARD_SIZE = 60

    while (cardSize <= MIN_CARD_SIZE && columns < totalCards) {
        columns += 1
        rows = Math.ceil(totalCards / columns)
        cardSize = calcCardSize()
    }

    cardSize = Math.min(cardSize, MAX_CARD_SIZE)

    cardsContainer.style.gridTemplateColumns = `repeat(${columns}, ${cardSize}px)`
    cardsContainer.style.gridAutoRows = `${cardSize}px`
}

const setupCardsLayout = (totalCards: number) => {
    if (detachCardsResize) {
        detachCardsResize()
        detachCardsResize = null
    }

    const update = () => adjustCardsLayout(totalCards)

    window.addEventListener('resize', update)
    detachCardsResize = () => window.removeEventListener('resize', update)

    requestAnimationFrame(update)
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
                renderCards()
                alert(data.message || 'Partida actualizada correctamente')
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
                renderCards()
                alert(data.message || 'La partida ha comenzado')
            } else {
                alert(data.message || 'Error al iniciar la partida')
            }
        } catch (error) {
            console.error('Error al iniciar la partida:', error)
            alert('Error de conexión al iniciar la partida')
        } finally {
            isStartingGame = false
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

/**
 * Renderiza las cartas de votación dinámicamente
 */
const renderCards = () => {
    const cardsContainer = document.getElementById('cardsContainer')
    if (!cardsContainer || !gameData) return

    cardsContainer.innerHTML = ''

    const maxPlayers = gameData.max_players || 30
    const players = gameData.players || []
    
    if (Array.isArray(players)) {
        gameData.current_players = players.length
    }
    
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

    setupCardsLayout(maxPlayers)
    updateStartButtonState()
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

    currentGameId = gameId
    currentUserId = await resolveCurrentUserId()
    bindEditButton(gameId)
    bindStartButton(gameId)
    bindAbandonButton(gameId)
    updateStartButtonState()

    // Cargar información de la partida
    await loadGameInfo(gameId)
}

init()

