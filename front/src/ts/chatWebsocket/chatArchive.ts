/**
 * Funciones para cargar el historial de mensajes del chat
 */

import { getApiConfig } from './connection/config';
import { getToken } from '../auth';

/**
 * Interfaz para un mensaje del chat
 */
export interface ChatMessage {
    id: number;
    type: 'public' | 'private' | 'group';
    message: string;
    user_id: number;
    user_name: string | null;
    user_nickname: string | null;
    user_image: string | null;
    recipient_id: number | null;
    recipient_ids: number[] | null;
    created_at: string;
    game_id: number;
}

/**
 * Interfaz para la respuesta del historial
 */
interface HistoryResponse {
    success: boolean;
    data?: ChatMessage[];
    message?: string;
}

/**
 * Carga el historial de mensajes de una partida
 * @param gameId - ID de la partida
 * @returns Promise con el array de mensajes o null si hay error
 */
export async function loadChatHistory(gameId: number): Promise<ChatMessage[] | null> {
    const token = getToken();
    if (!token) {
        console.warn('⚠️ No hay token de autenticación para cargar el historial');
        return null;
    }

    const apiConfig = getApiConfig();
    const url = `http://${apiConfig.host}:${apiConfig.port}/api/chat/${gameId}/history`;

    try {
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            console.error(`❌ Error al cargar historial: ${response.status} ${response.statusText}`);
            return null;
        }

        const data: HistoryResponse = await response.json();
        
        if (data.success && data.data) {
            console.log(`✅ Historial cargado: ${data.data.length} mensajes`);
            return data.data;
        } else {
            console.warn('⚠️ La respuesta no contiene datos válidos:', data.message);
            return null;
        }
    } catch (error) {
        console.error('❌ Error al cargar historial:', error);
        return null;
    }
}

