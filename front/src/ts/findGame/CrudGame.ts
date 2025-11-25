import routes from "../routes.ts"
import { getToken } from "../auth.ts"

const apiUrl = routes.gamesUrl

/**
 * Obtiene el token de autenticación dinámicamente
 * El backend validará el token y devolverá 401 si no es válido
 * @returns Token de autenticación o null si no existe
 */
const getAuthToken = (): string | null => {
    return getToken()
}

const getGames = async (page: number = 1) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}?page=${page}`, {
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

const getFilterGames = async (name: string, page: number = 1) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}?name=${encodeURIComponent(name)}&page=${page}`, {
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

const createGame = async (data: any) => {
    try {
        const token = getAuthToken()
        return await fetch(apiUrl, {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const joinGame = async (id: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${id}/join`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const viewGame = async (id: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const updateGame = async (id: number, data: any) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${id}`, {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const deleteGame = async (id: number) => {
    try {
        const token = getAuthToken()
        return await fetch(`${apiUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

export {
    getGames,
    getFilterGames,
    createGame,
    joinGame,
    viewGame,
    updateGame,
    deleteGame
}

