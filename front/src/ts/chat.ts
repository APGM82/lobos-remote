import Pusher from 'pusher-js';
import { 
    loadConfig,
    getReverbConfig,
    getApiConfig
} from './chatWebsocket/connection/config';
import { loadChatHistory } from './chatWebsocket/chatArchive';
import { getToken } from './auth';
import routes from './routes';

// Referencias DOM
const lblOn = document.querySelector('#lblOn') as HTMLElement;
const lblOff = document.querySelector('#lblOff') as HTMLElement;
const txtMensaje = document.querySelector('#txtMensaje') as HTMLInputElement;
const btnEnviar = document.querySelector('#btnEnviar') as HTMLButtonElement;
const ulMessages = document.querySelector('#messages') as HTMLUListElement;
const selectMessageType = document.querySelector('#messageType') as HTMLSelectElement;
const selectRecipient = document.querySelector('#recipient') as HTMLSelectElement;
const selectRecipientsContainer = document.querySelector('#recipients') as HTMLElement;

// Variables globales
let pusher: Pusher | null = null;
let channels: any[] = [];
let currentGameId: number | null = null;
let currentUserId: number | null = null;
let gamePlayers: any[] = [];

// ======== Utilidades ========

/**
 * Obtiene el ID de la partida desde la URL
 */
function getGameIdFromUrl(): number | null {
    const urlParams = new URLSearchParams(window.location.search);
    const gameId = urlParams.get('gameId');
    return gameId ? parseInt(gameId, 10) : null;
}

/**
 * Obtiene el ID del usuario actual
 */
async function getCurrentUserId(): Promise<number | null> {
    const token = getToken();
    if (!token) return null;

    try {
        const response = await fetch(`${routes.profileUrl}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            const data = await response.json();
            if (data.success && data.data?.id) {
                return data.data.id;
            }
        }
    } catch (error) {
        console.error('Error al obtener usuario actual:', error);
    }
    return null;
}

/**
 * Carga los jugadores de la partida
 */
async function loadGamePlayers(gameId: number): Promise<void> {
    const token = getToken();
    if (!token) return;

    try {
        const response = await fetch(`${routes.gamesUrl}/${gameId}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });

        if (response.ok) {
            const data = await response.json();
            if (data.success && data.data?.players) {
                gamePlayers = data.data.players;
                updateRecipientSelects();
            }
        }
    } catch (error) {
        console.error('Error al cargar jugadores:', error);
    }
}

/**
 * Actualiza los selectores de destinatarios según el tipo de mensaje
 */
function updateRecipientSelects(): void {
    const recipientSelector = document.querySelector('.recipient-selector') as HTMLElement;
    const recipientsSelector = document.querySelector('.recipients-selector') as HTMLElement;
    
    if (!selectRecipient || !selectRecipientsContainer || !recipientSelector || !recipientsSelector) return;

    const messageType = selectMessageType?.value || 'public';

    // Limpiar opciones existentes
    selectRecipient.innerHTML = '<option value="">Selecciona destinatario...</option>';
    selectRecipientsContainer.innerHTML = '';

    if (messageType === 'private') {
        recipientSelector.style.display = 'block';
        recipientsSelector.style.display = 'none';
        
        // Agregar jugadores (excluyendo al usuario actual)
        gamePlayers.forEach(player => {
            if (player.id !== currentUserId) {
                const option = document.createElement('option');
                option.value = player.id.toString();
                option.textContent = `${player.name} (${player.nickname})`;
                selectRecipient.appendChild(option);
            }
        });
    } else if (messageType === 'group') {
        recipientSelector.style.display = 'none';
        recipientsSelector.style.display = 'block';
        
        // Agregar checkboxes para seleccionar múltiples jugadores
        gamePlayers.forEach(player => {
            if (player.id !== currentUserId) {
                const label = document.createElement('label');
                label.style.display = 'block';
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = player.id.toString();
                checkbox.name = 'recipient';
                
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(` ${player.name} (${player.nickname})`));
                selectRecipientsContainer.appendChild(label);
            }
        });
    } else {
        recipientSelector.style.display = 'none';
        recipientsSelector.style.display = 'none';
    }
}

// ======== Estado Online / Offline ========
function setOnline(): void {
    if (lblOn) lblOn.style.display = '';
    if (lblOff) lblOff.style.display = 'none';
}

function setOffline(): void {
    if (lblOn) lblOn.style.display = 'none';
    if (lblOff) lblOff.style.display = '';
}

// ======== Renderizar mensajes ========
function renderMessage(data: any): void {
    const li = document.createElement('li');
    li.classList.add('message-item');

    // Indicador de tipo de mensaje
    let typeLabel = '';
    if (data.type === 'private') {
        typeLabel = '<span class="message-type private">🔒 Privado</span>';
    } else if (data.type === 'group') {
        typeLabel = '<span class="message-type group">👥 Grupo</span>';
    } else {
        typeLabel = '<span class="message-type public">🌐 Público</span>';
    }

    // Información del usuario
    const userInfo = `
        <div class="message-user">
            <img src="${data.user_image || '../public/default-avatar.png'}" alt="${data.user_name}" class="message-avatar" />
            <span class="message-username">${data.user_name || data.user_nickname || 'Usuario'}</span>
            ${data.user_nickname && data.user_nickname !== data.user_name ? `<span class="message-nickname">@${data.user_nickname}</span>` : ''}
        </div>
    `;

    // Timestamp
    const timestamp = new Date(data.created_at).toLocaleTimeString('es-ES', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });

    li.innerHTML = `
        ${typeLabel}
        ${userInfo}
        <div class="message-content">${data.message}</div>
        <div class="message-time">${timestamp}</div>
    `;

    ulMessages.appendChild(li);
    ulMessages.scrollTop = ulMessages.scrollHeight;
}

// ======== Cargar historial ========
async function loadHistory(gameId: number): Promise<void> {
    const messages = await loadChatHistory(gameId);
    
    if (messages && messages.length > 0) {
        // Limpiar mensajes existentes
        ulMessages.innerHTML = '';
        
        // Renderizar mensajes del historial
        messages.forEach((msg) => {
            renderMessage(msg);
        });
    }
}

// ======== Inicializar conexión WebSocket ========
async function initWebSocket(): Promise<void> {
    if (!currentGameId) {
        console.error('No se pudo obtener gameId desde la URL');
        alert('Error: No se especificó el ID de la partida. Redirigiendo...');
        window.location.href = routes.findGame;
        return;
    }

    // Cargar configuración desde la API
    await loadConfig();
    
    // Obtener configuración actualizada
    const reverbConfig = getReverbConfig();
    const apiConfig = getApiConfig();

    // Determinar el host WebSocket
    const wsHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
        ? 'localhost'
        : reverbConfig.host === '127.0.0.1'
        ? 'localhost'
        : reverbConfig.host;

    // Configuración Pusher para Reverb
    const token = getToken();
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
    });

    // Suscribirse a canales según el tipo
    // Canal público
    const publicChannel = pusher.subscribe(`chat.game.${currentGameId}.public`);
    channels.push(publicChannel);

    // Canales privados (para el usuario actual)
    // Nota: Laravel agrega automáticamente el prefijo "private-" cuando usamos PrivateChannel
    // En Pusher, debemos suscribirnos con el prefijo "private-" y Laravel validará la autorización
    if (currentUserId && token) {
        const privateChannel = pusher.subscribe(`private-chat.game.${currentGameId}.private.${currentUserId}`);
        channels.push(privateChannel);
        
        // Canal de grupo (para el usuario actual)
        const groupChannel = pusher.subscribe(`private-chat.game.${currentGameId}.group.${currentUserId}`);
        channels.push(groupChannel);
    }

    // Eventos globales de conexión
    pusher.connection.bind('connected', () => {
        console.info('✅ Conectado correctamente a Reverb');
        setOnline();
    });

    pusher.connection.bind('error', (err: any) => {
        if (err.data && err.data.code === 1006) {
            console.warn('⚠️ Conexión perdida con Reverb');
        } else {
            console.error('⚠️ Error WebSocket:', err);
        }
        setOffline();
    });

    // Handler para mensajes públicos
    publicChannel.bind('message.sent', (data: any) => {
        if (data.type === 'public' && data.game_id === currentGameId) {
            console.log('Mensaje público recibido:', data);
            renderMessage(data);
        }
    });

    // Handler para mensajes privados
    if (currentUserId && channels.length > 1) {
        const privateChannel = channels.find(c => c.name && c.name.includes(`private.${currentUserId}`));
        if (privateChannel) {
            privateChannel.bind('message.sent', (data: any) => {
                if (data.type === 'private' && 
                    data.game_id === currentGameId &&
                    (data.user_id === currentUserId || data.recipient_id === currentUserId)) {
                    console.log('Mensaje privado recibido:', data);
                    renderMessage(data);
                }
            });
        }

        // Handler para mensajes de grupo
        const groupChannel = channels.find(c => c.name && c.name.includes(`group.${currentUserId}`));
        if (groupChannel) {
            groupChannel.bind('message.sent', (data: any) => {
                if (data.type === 'group' && 
                    data.game_id === currentGameId &&
                    (data.user_id === currentUserId || 
                     (data.recipient_ids && data.recipient_ids.includes(currentUserId)))) {
                    console.log('Mensaje de grupo recibido:', data);
                    renderMessage(data);
                }
            });
        }
    }
}

// ======== Enviar mensajes ========
async function sendPublicMessage(message: string): Promise<void> {
    if (!currentGameId) return;

    const token = getToken();
    if (!token) {
        alert('No estás autenticado. Por favor, inicia sesión.');
        window.location.href = routes.login;
        return;
    }

    const apiConfig = getApiConfig();
    const url = `http://${apiConfig.host}:${apiConfig.port}/api/chat/${currentGameId}/send`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ message })
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Mensaje público enviado:', data);
            txtMensaje.value = '';
        } else {
            const error = await response.json();
            alert(error.message || 'Error al enviar el mensaje');
        }
    } catch (error) {
        console.error('Error al enviar mensaje público:', error);
        alert('Error de conexión al enviar el mensaje');
    }
}

async function sendPrivateMessage(message: string, recipientId: number): Promise<void> {
    if (!currentGameId) return;

    const token = getToken();
    if (!token) {
        alert('No estás autenticado. Por favor, inicia sesión.');
        window.location.href = routes.login;
        return;
    }

    const apiConfig = getApiConfig();
    const url = `http://${apiConfig.host}:${apiConfig.port}/api/chat/${currentGameId}/send-private`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                message,
                recipient_id: recipientId
            })
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Mensaje privado enviado:', data);
            txtMensaje.value = '';
            if (selectRecipient) selectRecipient.value = '';
        } else {
            const error = await response.json();
            alert(error.message || 'Error al enviar el mensaje privado');
        }
    } catch (error) {
        console.error('Error al enviar mensaje privado:', error);
        alert('Error de conexión al enviar el mensaje');
    }
}

async function sendGroupMessage(message: string, recipientIds: number[]): Promise<void> {
    if (!currentGameId) return;

    const token = getToken();
    if (!token) {
        alert('No estás autenticado. Por favor, inicia sesión.');
        window.location.href = routes.login;
        return;
    }

    const apiConfig = getApiConfig();
    const url = `http://${apiConfig.host}:${apiConfig.port}/api/chat/${currentGameId}/send-group`;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ 
                message,
                recipient_ids: recipientIds
            })
        });

        if (response.ok) {
            const data = await response.json();
            console.log('Mensaje de grupo enviado:', data);
            txtMensaje.value = '';
            // Limpiar checkboxes
            if (selectRecipientsContainer) {
                const checkboxes = selectRecipientsContainer.querySelectorAll('input[type="checkbox"]');
                checkboxes.forEach((cb: any) => cb.checked = false);
            }
        } else {
            const error = await response.json();
            alert(error.message || 'Error al enviar el mensaje de grupo');
        }
    } catch (error) {
        console.error('Error al enviar mensaje de grupo:', error);
        alert('Error de conexión al enviar el mensaje');
    }
}

// ======== Event listeners ========
btnEnviar.addEventListener('click', () => {
    const mensaje = txtMensaje.value.trim();
    if (!mensaje) {
        console.warn('⚠️ El mensaje está vacío');
        return;
    }

    const messageType = selectMessageType?.value || 'public';

    if (messageType === 'public') {
        sendPublicMessage(mensaje);
    } else if (messageType === 'private') {
        const recipientId = selectRecipient?.value;
        if (!recipientId) {
            alert('Por favor, selecciona un destinatario');
            return;
        }
        sendPrivateMessage(mensaje, parseInt(recipientId, 10));
    } else if (messageType === 'group') {
        const checkboxes = selectRecipientsContainer?.querySelectorAll('input[type="checkbox"]:checked');
        if (!checkboxes || checkboxes.length < 2) {
            alert('Por favor, selecciona al menos 2 destinatarios para un mensaje de grupo');
            return;
        }
        const recipientIds = Array.from(checkboxes).map((cb: any) => parseInt(cb.value, 10));
        sendGroupMessage(mensaje, recipientIds);
    }
});

// Permitir enviar con Enter
txtMensaje.addEventListener('keypress', (e: KeyboardEvent) => {
    if (e.key === 'Enter') {
        btnEnviar.click();
    }
});

// Actualizar selectores cuando cambia el tipo de mensaje
if (selectMessageType) {
    selectMessageType.addEventListener('change', updateRecipientSelects);
}

// Inicializar cuando el DOM esté listo
(async () => {
    // Estado inicial
    setOffline();
    
    // Obtener gameId desde URL
    currentGameId = getGameIdFromUrl();
    if (!currentGameId) {
        alert('No se especificó el ID de la partida. Redirigiendo...');
        window.location.href = routes.findGame;
        return;
    }

    // Obtener usuario actual
    currentUserId = await getCurrentUserId();
    if (!currentUserId) {
        alert('No estás autenticado. Redirigiendo al login...');
        window.location.href = routes.login;
        return;
    }

    // Cargar jugadores de la partida
    await loadGamePlayers(currentGameId);
    
    // Cargar historial
    await loadHistory(currentGameId);
    
    // Inicializar WebSocket
    await initWebSocket();
})();
