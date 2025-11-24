import { getGames, getFilterGames, createGame, joinGame, viewGame, updateGame, deleteGame } from './CrudGame.ts'
import { requireAuth, getToken } from '../auth.ts'

let selectedGame: any = null
let currentUser: any = null
let isAdmin = false
let currentPage = 1 // Página actual de la paginación
let currentFilter = '' // Filtro de búsqueda actual para mantenerlo al cambiar de página
let paginationInfo: any = null // Información de paginación recibida del backend

// Verificar si el usuario es admin
const checkAdminStatus = async () => {
    try {
        const token = getToken()
        if (!token) return false

        const response = await fetch('http://127.0.0.1:8000/api/userToken', {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        })

        if (response.ok) {
            const data = await response.json()
            currentUser = data.data.user
            const roles = data.data.role || []
            isAdmin = roles.some((role: any) => role.name === 'admin')
            return isAdmin
        }
    } catch (error) {
        console.error('Error al verificar admin:', error)
    }
    return false
}

const filter = (document.getElementById('filter') as HTMLInputElement)!

/**
 * Mostrar partidas con paginación
 * Carga las partidas de la página actual y renderiza la tabla junto con los controles de paginación
 */
const showGames = () => {
    // Si hay un filtro activo, usar filterGames, sino usar loadGames
    if (currentFilter.trim()) {
        filterGames(currentFilter).then(result => {
            handleGamesResponse(result)
        })
    } else {
        loadGames().then(result => {
            handleGamesResponse(result)
        })
    }
}

/**
 * Maneja la respuesta de las partidas (con o sin paginación)
 * @param result - Objeto con datos y paginación, o array de partidas
 */
const handleGamesResponse = (result: any) => {
    let games: any[] = []
    let pagination: any = null

    // Verificar si la respuesta incluye paginación
    if (result && typeof result === 'object' && 'data' in result && 'pagination' in result) {
        games = result.data || []
        pagination = result.pagination
        paginationInfo = pagination
        
        // Si la página actual quedó vacía y hay una página anterior, ir a esa
        if (games.length === 0 && pagination && pagination.last_page > 0 && currentPage > pagination.last_page) {
            currentPage = pagination.last_page
            // Recargar con la página ajustada
            showGames()
            return
        }
        
        // Actualizar currentPage con la página que realmente devolvió el backend
        if (pagination && pagination.current_page) {
            currentPage = pagination.current_page
        }
    } else if (Array.isArray(result)) {
        // Compatibilidad con formato antiguo (sin paginación)
        games = result
    }

    if (games.length === 0) {
        // Ocultar la tabla y mostrar el mensaje de error
        const table = document.querySelector('#tableContainer table')
        table?.classList.add('disabled')

        const gamesError = document.getElementById('gamesError')
        gamesError?.classList.remove('disabled')
        
        // Limpiar paginación si no hay resultados
        renderPagination(null)
    } else {
        // Mostrar la tabla y ocultar el error
        const table = document.querySelector('#tableContainer table')
        table?.classList.remove('disabled')

        const gamesError = document.getElementById('gamesError')
        gamesError?.classList.add('disabled')

        chargeTable(games)
        renderPagination(pagination)
    }
}

/**
 * Event listener para el filtro de búsqueda
 * Al escribir, resetea a la página 1 y aplica el filtro
 */
filter.addEventListener('keyup', async () => {
    currentFilter = filter.value
    currentPage = 1 // Resetear a la primera página al filtrar
    showGames() // Recargar con el nuevo filtro
})

const chargeTable = (games: any[]) => {
    const table = document.getElementById('gamesTable')!
    table.innerHTML = ""
    
    games.forEach((game) => {
        const row = document.createElement('tr')

        // Columna Nombre
        const tdName = document.createElement('td')
        tdName.classList.add('text-center')
        tdName.textContent = game.name
        row.appendChild(tdName)

        // Columna Código
        const tdCode = document.createElement('td')
        tdCode.classList.add('text-center')
        const codeSpan = document.createElement('span')
        codeSpan.textContent = game.code_join_to || 'N/A'
        codeSpan.style.fontFamily = 'monospace'
        codeSpan.style.fontWeight = 'bold'
        codeSpan.style.fontSize = '0.9em'
        codeSpan.style.letterSpacing = '1px'
        tdCode.appendChild(codeSpan)
        row.appendChild(tdCode)

        // Columna Máximo de Jugadores
        const tdMaxPlayers = document.createElement('td')
        tdMaxPlayers.classList.add('text-center')
        tdMaxPlayers.textContent = `${game.current_players || 0}/${game.max_players}`
        row.appendChild(tdMaxPlayers)

        // Columna Estado
        const tdStatus = document.createElement('td')
        tdStatus.classList.add('text-center')
        const statusBadge = document.createElement('span')
        statusBadge.classList.add('status-badge')
        
        let statusText = ''
        let statusClass = ''
        
        const status = game.status?.toLowerCase() || game.status
        
        switch(status) {
            case 'waiting':
                statusText = 'Esperando'
                statusClass = 'status-waiting'
                break
            case 'in_progress':
            case 'en_progreso':
                statusText = 'En Progreso'
                statusClass = 'status-in-progress'
                break
            case 'finished':
            case 'finalizada':
                statusText = 'Finalizada'
                statusClass = 'status-finished'
                break
            case 'created':
            case 'creada':
                statusText = 'Creada'
                statusClass = 'status-created'
                break
            default:
                statusText = game.status || 'Desconocido'
                statusClass = 'status-waiting'
        }
        
        statusBadge.textContent = statusText
        statusBadge.classList.add(statusClass)
        tdStatus.appendChild(statusBadge)
        row.appendChild(tdStatus)

        // Columna Acciones
        const tdActions = document.createElement('td')
        tdActions.classList.add('d-flex', 'align-items-center', 'justify-content-center', 'text-center', 'h-100')

        const div = document.createElement('div')
        div.classList.add('actions-container', 'bg-white', 'border-none')
        tdActions.appendChild(div)

        // Verificar permisos: admin puede editar/eliminar cualquier partida, user solo si es host
        const isHost = currentUser && game.host && currentUser.id === game.host.id
        const canEdit = isAdmin || isHost
        // Admin puede eliminar cualquier partida, user solo puede eliminar si es host
        const canDelete = isAdmin || isHost

        // Botón Unirse (solo si el estado NO es "in_progress" o "finished")
        if (status !== 'in_progress' && status !== 'en_progreso' && status !== 'finished' && status !== 'finalizada') {
            const joinButton = document.createElement('button')
            joinButton.classList.add('btn', 'success')
            joinButton.value = game.id.toString()
            joinButton.textContent = 'Unirse'
            joinButton.addEventListener('click', () => {
                handleJoinGame(game.id)
            })
            div.appendChild(joinButton)
        }

        // Botón Ver Detalles (siempre visible)
        const viewButton = document.createElement('button')
        viewButton.classList.add('btn', 'warning')
        viewButton.value = game.id.toString()
        viewButton.textContent = 'Ver Detalles'
        viewButton.addEventListener('click', () => {
            handleViewGame(game.id)
        })
        div.appendChild(viewButton)

        // Botón Editar (solo si es admin o host)
        if (canEdit && status !== 'in_progress' && status !== 'en_progreso' && status !== 'finished' && status !== 'finalizada') {
            const editButton = document.createElement('button')
            editButton.classList.add('btn', 'warning')
            editButton.value = game.id.toString()
            editButton.textContent = 'Editar'
            editButton.addEventListener('click', () => {
                selectedGame = game
                handleEditGame(game)
            })
            div.appendChild(editButton)
        }

        // Botón Eliminar: admin puede eliminar cualquier partida, user solo si es host
        if (canDelete) {
            const deleteButton = document.createElement('button')
            deleteButton.classList.add('btn', 'danger')
            deleteButton.value = game.id.toString()
            deleteButton.textContent = 'Eliminar'
            deleteButton.addEventListener('click', () => {
                selectedGame = game
                handleDeleteGame(game.id, game.name)
            })
            div.appendChild(deleteButton)
        }

        row.appendChild(tdActions)
        table.appendChild(row)
    })
}

/**
 * Cargar partidas con paginación
 * @returns Promise con objeto que contiene data (array de partidas) y pagination (metadatos)
 */
const loadGames = async (): Promise<any> => {
    try {
        const response = await getGames(currentPage)
        
        // Verificar si la respuesta es OK
        if (!response.ok) {
            // Si es 401, redirigir a login
            if (response.status === 401) {
                window.location.href = 'login.html'
                return { data: [], pagination: null }
            }
            
            // Intentar obtener el texto de error
            const text = await response.text()
            console.error('Error del servidor:', response.status, text)
            showError(`Error al cargar las partidas (${response.status})`)
            return { data: [], pagination: null }
        }
        
        // Verificar que la respuesta sea JSON
        const contentType = response.headers.get('content-type')
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text()
            console.error('Respuesta no es JSON:', text.substring(0, 200))
            showError('Error: El servidor no devolvió JSON')
            return { data: [], pagination: null }
        }
        
        const data = await response.json()
        
        if (data.success && data.data) {
            // Devolver tanto los datos como la información de paginación
            return {
                data: data.data,
                pagination: data.pagination || null
            }
        }
        return { data: [], pagination: null }
    } catch (e) {
        console.error("Error al cargar las partidas:", e)
        showError('Error de conexión al cargar las partidas')
        return { data: [], pagination: null }
    }
}

/**
 * Filtrar partidas por nombre o código con paginación
 * @param name - Término de búsqueda
 * @returns Promise con objeto que contiene data (array de partidas) y pagination (metadatos)
 */
const filterGames = async (name: string): Promise<any> => {
    try {
        if (!name || name.trim() === '') {
            // Si no hay filtro, cargar todas las partidas
            return await loadGames()
        }
        
        const response = await getFilterGames(name.trim(), currentPage)
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return { data: [], pagination: null }
            }
            return { data: [], pagination: null }
        }
        
        const contentType = response.headers.get('content-type')
        if (!contentType || !contentType.includes('application/json')) {
            console.error('Respuesta no es JSON al filtrar')
            return { data: [], pagination: null }
        }
        
        const data = await response.json()
        
        if (data.success && data.data) {
            // Devolver tanto los datos como la información de paginación
            return {
                data: data.data,
                pagination: data.pagination || null
            }
        }
        return { data: [], pagination: null }
    } catch (e) {
        console.error("Error al filtrar las partidas:", e)
        return { data: [], pagination: null }
    }
}

/**
 * Cambiar a una página específica
 * @param page - Número de página a la que cambiar
 */
const changePage = (page: number) => {
    if (page < 1 || (paginationInfo && page > paginationInfo.last_page)) {
        return // No hacer nada si la página es inválida
    }
    currentPage = page
    showGames() // Recargar partidas de la nueva página
}

/**
 * Renderizar los controles de paginación
 * @param pagination - Objeto con información de paginación (current_page, last_page, total, etc.)
 */
const renderPagination = (pagination: any) => {
    const container = document.getElementById('paginationContainer')
    if (!container) return

    // Limpiar contenedor
    container.innerHTML = ''

    // Si no hay información de paginación o solo hay una página, no mostrar controles
    if (!pagination || pagination.last_page <= 1) {
        return
    }

    const { current_page, last_page } = pagination

    // Crear contenedor principal
    const paginationDiv = document.createElement('div')
    paginationDiv.classList.add('pagination-container')

    // Contenedor para números de página
    const pageNumbersDiv = document.createElement('div')
    pageNumbersDiv.classList.add('pagination-numbers')

    // Calcular qué números de página mostrar (máximo 5 alrededor de la página actual)
    let startPage = Math.max(1, current_page - 2)
    let endPage = Math.min(last_page, current_page + 2)

    // Ajustar si estamos cerca del inicio o del final
    if (endPage - startPage < 4) {
        if (startPage === 1) {
            endPage = Math.min(last_page, startPage + 4)
        } else if (endPage === last_page) {
            startPage = Math.max(1, endPage - 4)
        }
    }

    // Mostrar primera página si no está en el rango
    if (startPage > 1) {
        const firstBtn = document.createElement('button')
        firstBtn.classList.add('pagination-number')
        firstBtn.textContent = '1'
        firstBtn.addEventListener('click', () => changePage(1))
        pageNumbersDiv.appendChild(firstBtn)

        if (startPage > 2) {
            const ellipsis = document.createElement('button')
            ellipsis.classList.add('pagination-ellipsis')
            ellipsis.textContent = '...'
            ellipsis.disabled = true
            pageNumbersDiv.appendChild(ellipsis)
        }
    }

    // Mostrar números de página en el rango calculado
    for (let i = startPage; i <= endPage; i++) {
        const pageBtn = document.createElement('button')
        pageBtn.classList.add('pagination-number')
        if (i === current_page) {
            pageBtn.classList.add('active')
        }
        pageBtn.textContent = i.toString()
        pageBtn.addEventListener('click', () => changePage(i))
        pageNumbersDiv.appendChild(pageBtn)
    }

    // Mostrar última página si no está en el rango
    if (endPage < last_page) {
        if (endPage < last_page - 1) {
            const ellipsis = document.createElement('button')
            ellipsis.classList.add('pagination-ellipsis')
            ellipsis.textContent = '...'
            ellipsis.disabled = true
            pageNumbersDiv.appendChild(ellipsis)
        }

        const lastBtn = document.createElement('button')
        lastBtn.classList.add('pagination-number')
        lastBtn.textContent = last_page.toString()
        lastBtn.addEventListener('click', () => changePage(last_page))
        pageNumbersDiv.appendChild(lastBtn)
    }

    paginationDiv.appendChild(pageNumbersDiv)

    container.appendChild(paginationDiv)
}

const handleJoinGame = async (gameId: number) => {
    try {
        const response = await joinGame(gameId)
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return
            }
            const text = await response.text()
            try {
                const data = JSON.parse(text)
                alert(data.message || 'Error al unirse a la partida')
            } catch {
                alert(`Error ${response.status}: ${text.substring(0, 100)}`)
            }
            return
        }
        
        const data = await response.json()

        if (data.success) {
            alert('Te has unido a la partida correctamente')
            currentPage = 1 // Resetear a la primera página
            showGames()
        } else {
            alert(data.message || 'Error al unirse a la partida')
        }
    } catch (error) {
        console.error('Error al unirse a la partida:', error)
        alert('Error de conexión al unirse a la partida')
    }
}

const handleViewGame = async (gameId: number) => {
    try {
        const response = await viewGame(gameId)
        
        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return
            }
            const text = await response.text()
            try {
                const data = JSON.parse(text)
                alert(data.message || 'Error al obtener los detalles de la partida')
            } catch {
                alert(`Error ${response.status} al obtener los detalles`)
            }
            return
        }
        
        const data = await response.json()

        if (data.success && data.data) {
            const game = data.data
            showGameDetailsModal(game)
        } else {
            alert(data.message || 'Error al obtener los detalles de la partida')
        }
    } catch (error) {
        console.error('Error al ver detalles de la partida:', error)
        alert('Error de conexión al obtener los detalles')
    }
}

const handleEditGame = (game: any) => {
    const editModal = document.getElementById('editGameModal') as HTMLElement
    const nameInput = document.getElementById('editGameName') as HTMLInputElement
    const maxPlayersInput = document.getElementById('editGameMaxPlayers') as HTMLInputElement

    nameInput.value = game.name
    maxPlayersInput.value = game.max_players.toString()

    editModal.style.display = 'flex'
}

const handleUpdateGame = async () => {
    if (!selectedGame) return

    const nameInput = document.getElementById('editGameName') as HTMLInputElement
    const maxPlayersInput = document.getElementById('editGameMaxPlayers') as HTMLInputElement

    const name = nameInput.value.trim()
    const maxPlayers = parseInt(maxPlayersInput.value)

    if (!name) {
        alert('El nombre es obligatorio')
        return
    }

    if (isNaN(maxPlayers) || maxPlayers < 4 || maxPlayers > 20) {
        alert('El número máximo de jugadores debe estar entre 4 y 20')
        return
    }

    try {
        const response = await updateGame(selectedGame.id, {
            name: name,
            max_players: maxPlayers
        })

        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
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
                alert(`Error ${response.status} al actualizar la partida`)
            }
            return
        }

        const data = await response.json()

        if (data.success) {
            alert('Partida actualizada correctamente')
            const editModal = document.getElementById('editGameModal') as HTMLElement
            editModal.style.display = 'none'
            currentPage = 1 // Resetear a la primera página
            showGames()
        } else {
            if (data.errors) {
                const errorMessages = Object.values(data.errors).flat()
                alert(errorMessages.join(', '))
            } else {
                alert(data.message || 'Error al actualizar la partida')
            }
        }
    } catch (error) {
        console.error('Error al actualizar la partida:', error)
        alert('Error de conexión al actualizar la partida')
    }
}

const handleDeleteGame = async (gameId: number, gameName: string) => {
    if (!confirm(`¿Estás seguro de que quieres eliminar la partida "${gameName}"?`)) {
        return
    }

    try {
        const response = await deleteGame(gameId)

        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return
            }
            const text = await response.text()
            try {
                const data = JSON.parse(text)
                alert(data.message || 'Error al eliminar la partida')
            } catch {
                alert(`Error ${response.status} al eliminar la partida`)
            }
            return
        }

        const data = await response.json()

        if (data.success) {
            alert('Partida eliminada correctamente')
            // Mantener la página actual en lugar de resetear a la primera
            showGames()
        } else {
            alert(data.message || 'Error al eliminar la partida')
        }
    } catch (error) {
        console.error('Error al eliminar la partida:', error)
        alert('Error de conexión al eliminar la partida')
    }
}

const handleCreateGame = () => {
    const createModal = document.getElementById('createGameModal') as HTMLElement
    const nameInput = document.getElementById('createGameName') as HTMLInputElement
    const maxPlayersInput = document.getElementById('createGameMaxPlayers') as HTMLInputElement

    nameInput.value = ''
    maxPlayersInput.value = '8'

    createModal.style.display = 'flex'
}

const handleSaveGame = async () => {
    const nameInput = document.getElementById('createGameName') as HTMLInputElement
    const maxPlayersInput = document.getElementById('createGameMaxPlayers') as HTMLInputElement

    const name = nameInput.value.trim()
    const maxPlayers = parseInt(maxPlayersInput.value)

    if (!name) {
        alert('El nombre es obligatorio')
        return
    }

    if (isNaN(maxPlayers) || maxPlayers < 4 || maxPlayers > 20) {
        alert('El número máximo de jugadores debe estar entre 4 y 20')
        return
    }

    try {
        const response = await createGame({
            name: name,
            max_players: maxPlayers
        })

        if (!response.ok) {
            if (response.status === 401) {
                window.location.href = 'login.html'
                return
            }
            const text = await response.text()
            try {
                const data = JSON.parse(text)
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat()
                    alert(errorMessages.join(', '))
                } else {
                    alert(data.message || 'Error al crear la partida')
                }
            } catch {
                alert(`Error ${response.status} al crear la partida`)
            }
            return
        }

        const data = await response.json()

        if (data.success) {
            alert('Partida creada correctamente')
            const createModal = document.getElementById('createGameModal') as HTMLElement
            createModal.style.display = 'none'
            currentPage = 1 // Resetear a la primera página para ver la nueva partida
            showGames()
        } else {
            if (data.errors) {
                const errorMessages = Object.values(data.errors).flat()
                alert(errorMessages.join(', '))
            } else {
                alert(data.message || 'Error al crear la partida')
            }
        }
    } catch (error) {
        console.error('Error al crear la partida:', error)
        alert('Error de conexión al crear la partida')
    }
}

const showGameDetailsModal = (game: any) => {
    const detailsModal = document.getElementById('gameDetailsModal') as HTMLElement
    const detailsContent = document.getElementById('gameDetailsContent') as HTMLElement

    const status = game.status?.name || game.status || 'Desconocido'
    const host = game.host || {}
    const players = game.players || []

    detailsContent.innerHTML = `
        <div style="text-align: left; padding: 10px;">
            <p><strong>Nombre:</strong> ${game.name}</p>
            <p><strong>Jugadores:</strong> ${game.current_players || 0}/${game.max_players}</p>
            <p><strong>Estado:</strong> ${status}</p>
            <p><strong>Código de unión:</strong> ${game.code_join_to || 'N/A'}</p>
            <p><strong>Host:</strong> ${host.name || host.nickname || 'N/A'}</p>
            <p><strong>Jugadores en la partida:</strong></p>
            <ul>
                ${players.map((player: any) => `<li>${player.name || player.nickname || 'Usuario'}</li>`).join('')}
            </ul>
            <p><strong>Creada:</strong> ${new Date(game.created_at).toLocaleString('es-ES')}</p>
        </div>
    `

    detailsModal.style.display = 'flex'
}

const showError = (message: string) => {
    const errorDiv = document.getElementById('gamesError')
    const errorText = document.getElementById('errorText')
    const table = document.querySelector('#tableContainer table')
    
    if (errorDiv && errorText) {
        errorText.textContent = message
        errorDiv.classList.remove('disabled')
    }
    
    // Ocultar solo la tabla, mantener visible el resto
    if (table) {
        table.classList.add('disabled')
    }
    
    // Limpiar paginación cuando hay error
    renderPagination(null)
}

const generateModals = () => {
    const body = document.querySelector('body')!

    // Modal para crear partida
    const createModal = document.createElement('div')
    createModal.id = 'createGameModal'
    createModal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>Crear Partida</h2>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="createGameName">Nombre de la partida:</label>
                        <input type="text" id="createGameName" placeholder="Nombre de la partida" required>
                    </div>
                    <div>
                        <label for="createGameMaxPlayers">Máximo de jugadores (4-20):</label>
                        <input type="number" id="createGameMaxPlayers" min="4" max="20" value="8" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="saveGameBtn" class="btn success">Crear</button>
                    <button id="closeCreateGameBtn" class="btn warning">Cerrar</button>
                </div>
            </div>
        </div>
    `
    createModal.style.display = 'none'
    body.appendChild(createModal)

    // Modal para editar partida
    const editModal = document.createElement('div')
    editModal.id = 'editGameModal'
    editModal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>Editar Partida</h2>
                </div>
                <div class="modal-body">
                    <div>
                        <label for="editGameName">Nombre de la partida:</label>
                        <input type="text" id="editGameName" placeholder="Nombre de la partida" required>
                    </div>
                    <div>
                        <label for="editGameMaxPlayers">Máximo de jugadores (4-20):</label>
                        <input type="number" id="editGameMaxPlayers" min="4" max="20" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="updateGameBtn" class="btn success">Guardar</button>
                    <button id="closeEditGameBtn" class="btn warning">Cerrar</button>
                </div>
            </div>
        </div>
    `
    editModal.style.display = 'none'
    body.appendChild(editModal)

    // Modal para ver detalles
    const detailsModal = document.createElement('div')
    detailsModal.id = 'gameDetailsModal'
    detailsModal.innerHTML = `
        <div class="modal-overlay">
            <div class="modal">
                <div class="modal-header">
                    <h2>Detalles de la Partida</h2>
                </div>
                <div class="modal-body">
                    <div id="gameDetailsContent"></div>
                </div>
                <div class="modal-footer">
                    <button id="closeDetailsGameBtn" class="btn warning">Cerrar</button>
                </div>
            </div>
        </div>
    `
    detailsModal.style.display = 'none'
    body.appendChild(detailsModal)

    // Event listeners para crear partida
    document.getElementById('saveGameBtn')?.addEventListener('click', handleSaveGame)
    document.getElementById('closeCreateGameBtn')?.addEventListener('click', () => {
        createModal.style.display = 'none'
    })

    // Event listeners para editar partida
    document.getElementById('updateGameBtn')?.addEventListener('click', handleUpdateGame)
    document.getElementById('closeEditGameBtn')?.addEventListener('click', () => {
        editModal.style.display = 'none'
    })

    // Event listeners para ver detalles
    document.getElementById('closeDetailsGameBtn')?.addEventListener('click', () => {
        detailsModal.style.display = 'none'
    })

    // Cerrar modales al hacer clic fuera
    createModal.addEventListener('click', (e) => {
        if ((e.target as HTMLElement).classList.contains('modal-overlay')) {
            createModal.style.display = 'none'
        }
    })

    editModal.addEventListener('click', (e) => {
        if ((e.target as HTMLElement).classList.contains('modal-overlay')) {
            editModal.style.display = 'none'
        }
    })

    detailsModal.addEventListener('click', (e) => {
        if ((e.target as HTMLElement).classList.contains('modal-overlay')) {
            detailsModal.style.display = 'none'
        }
    })
}

const init = async () => {
    // Verificar autenticación
    if (!requireAuth()) {
        return
    }

    // Verificar si es admin
    await checkAdminStatus()

    // Generar modales
    generateModals()

    // Configurar botón de crear partida
    const createGameBtn = document.getElementById('createGameBtn')
    createGameBtn?.addEventListener('click', handleCreateGame)

    // Cargar y mostrar partidas
    showGames()
}

export default init
