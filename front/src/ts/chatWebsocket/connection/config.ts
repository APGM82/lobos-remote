/**
 * Configuración de conexión para Reverb WebSocket y API
 * SOLAMENTE lee las variables desde la base de datos a través de la API
 * NO usa variables de entorno ni valores por defecto
 */

// Cache de configuración
let configCache: Record<string, string> | null = null;
let configLoaded: boolean = false;

/**
 * Cargar configuración desde la API
 * Esta es la ÚNICA fuente de configuración permitida
 * @throws Error si no se puede cargar la configuración
 */
export async function loadConfig(): Promise<Record<string, string>> {
    if (configCache !== null && configLoaded) {
        return configCache as Record<string, string>;
    }

    try {
        // Obtener la URL base desde window.location para construir la URL de la API
        // La API está en el mismo host pero puerto 8000
        // En desarrollo local, usar localhost; en producción, usar el hostname actual
        const apiHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'
            ? 'localhost'
            : window.location.hostname;
        const apiPort = '8000';
        const apiUrl = `http://${apiHost}:${apiPort}/api/vite-config`;
        
        console.log('📡 Cargando configuración desde la API:', apiUrl);
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (!data.config || Object.keys(data.config).length === 0) {
            throw new Error('La API no devolvió configuración válida');
        }
        
        configCache = data.config as Record<string, string>;
        configLoaded = true;
        
        console.log('✅ Configuración cargada desde la API:', configCache);
        
        return configCache;
    } catch (error) {
        console.error('❌ Error al cargar la configuración desde la API:', error);
        throw new Error(`No se pudo cargar la configuración desde la API: ${error instanceof Error ? error.message : String(error)}`);
    }
}

/**
 * Obtener valor de configuración
 * @throws Error si la configuración no ha sido cargada
 */
function getConfigValue(key: string): string {
    if (configCache === null || !configLoaded) {
        throw new Error(`La configuración no ha sido cargada. Llama a loadConfig() primero.`);
    }
    
    if (!configCache[key]) {
        throw new Error(`La clave de configuración '${key}' no existe en la base de datos`);
    }
    
    return configCache[key];
}

/**
 * Obtener configuración de Reverb con valores parseados y validados
 * @throws Error si la configuración no ha sido cargada o si hay valores inválidos
 */
export function getReverbConfig() {
    const port = parseInt(getConfigValue('VITE_REVERB_PORT'), 10);
    if (isNaN(port) || port < 1 || port > 65535) {
        throw new Error(`Puerto Reverb inválido: ${getConfigValue('VITE_REVERB_PORT')}`);
    }

    const scheme = getConfigValue('VITE_REVERB_SCHEME').toLowerCase();
    if (scheme !== 'http' && scheme !== 'https') {
        throw new Error(`Esquema Reverb inválido: ${scheme}. Debe ser 'http' o 'https'`);
    }

    return {
        appId: getConfigValue('VITE_REVERB_APP_ID').trim(),
        appKey: getConfigValue('VITE_REVERB_APP_KEY').trim(),
        host: getConfigValue('VITE_REVERB_HOST').trim(),
        port: port, // Parseado como número
        scheme: scheme as 'http' | 'https', // Validado y tipado
        cluster: getConfigValue('VITE_REVERB_APP_CLUSTER').trim()
    };
}

/**
 * Obtener configuración de API con valores parseados y validados
 * @throws Error si la configuración no ha sido cargada o si hay valores inválidos
 */
export function getApiConfig() {
    const port = parseInt(getConfigValue('VITE_API_PORT'), 10);
    if (isNaN(port) || port < 1 || port > 65535) {
        throw new Error(`Puerto API inválido: ${getConfigValue('VITE_API_PORT')}`);
    }

    return {
        host: getConfigValue('VITE_API_HOST').trim(),
        port: port // Parseado como número
    };
}

