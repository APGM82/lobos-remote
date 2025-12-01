import routes from "../routes.ts"
import { getToken } from "../auth.ts"

const apiUrl = routes.gamesUrl
/**
 * Obtiene el token de autenticación dinámicamente
 * @returns Token de autenticación o null si no existe
 */
const getAuthToken = (): string | null => {
    return getToken()
}

/**
 * Obtiene la información de una partida específica
 * @param gameId - ID de la partida
 * @returns Promise con la respuesta del servidor
 */
const getGameInfo = async (gameId: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${gameId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

/**
 * Abandona una partida
 * @param gameId - ID de la partida
 * @returns Promise con la respuesta del servidor
 */
const leaveGame = async (gameId: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${gameId}/leave`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

/**
 * Inicia una partida
 * @param gameId - ID de la partida
 * @returns Promise con la respuesta del servidor
 */
const startGame = async (gameId: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${gameId}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({})
        });
    } catch (error) {
        throw error
    }
}

/**
 * Actualiza los detalles de una partida
 * @param gameId - ID de la partida
 * @param payload - Datos a actualizar
 * @returns Promise con la respuesta del servidor
 */
const updateGameDetails = async (gameId: number, payload: { name: string; max_players: number }) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${gameId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify(payload)
        });
    } catch (error) {
        throw error
    }
}

export {
    getGameInfo,
    leaveGame,
    startGame,
    updateGameDetails
}

