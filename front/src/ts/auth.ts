/**
 * Utilidades de autenticación
 * Funciones reutilizables para manejar la autenticación en el frontend
 */

/**
 * Obtiene el token de autenticación del sessionStorage
 * @returns Token de autenticación o null si no existe
 */
export function getToken(): string | null {
    return sessionStorage.getItem('token');
}

/**
 * Obtiene los datos del usuario del sessionStorage
 * @returns Datos del usuario o null si no existe
 */
export function getUser(): { id: number; name: string; email: string } | null {
    const userStr = sessionStorage.getItem('user');
    if (!userStr) return null;
    try {
        return JSON.parse(userStr);
    } catch {
        return null;
    }
}

/**
 * Verifica si el usuario está autenticado
 * @returns true si el usuario está autenticado, false en caso contrario
 */
export function isAuthenticated(): boolean {
    return getToken() !== null;
}

/**
 * Redirige a la página de login si el usuario NO está autenticado
 * Útil para páginas protegidas (perfil, admin, etc.)
 * @param redirectTo - Ruta a la que redirigir si no está autenticado (default: 'login.html')
 */
export function requireAuth(redirectTo: string = 'login.html'): boolean {
    if (!isAuthenticated()) {
        window.location.href = redirectTo;
        return false;
    }
    return true;
}

/**
 * Redirige si el usuario YA está autenticado
 * Útil para páginas públicas que no deben ser accesibles si ya estás logueado (login, register)
 * @param redirectTo - Ruta a la que redirigir si está autenticado (default: 'profile.html')
 * @returns false si redirigió, true si no está autenticado y puede continuar
 */
export function redirectIfAuthenticated(redirectTo: string = 'profile.html'): boolean {
    if (isAuthenticated()) {
        window.location.href = redirectTo;
        return false;
    }
    return true;
}

/**
 * Limpia la sesión del usuario (logout)
 */
export function clearSession(): void {
    sessionStorage.removeItem('token');
    sessionStorage.removeItem('user');
}

/**
 * Guarda la sesión del usuario después del login
 * @param token - Token de autenticación
 * @param user - Datos del usuario
 */
export function saveSession(token: string, user: { id: number; name: string; email: string }): void {
    sessionStorage.setItem('token', token);
    sessionStorage.setItem('user', JSON.stringify(user));
}

