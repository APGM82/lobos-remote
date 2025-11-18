import { requireAuth, getToken, clearSession } from './auth.ts';

const API_URL = 'http://127.0.0.1:8000/api';

interface UserProfile {
    id: number;
    name: string;
    nickname: string;
    email: string;
    image: string | null;
    roles: Array<{ id: number; name: string }>;
}

document.addEventListener('DOMContentLoaded', () => {
    // Verificar autenticación (redirigir a login si no está autenticado)
    if (!requireAuth()) {
        return;
    }
    // Elementos del DOM
    const profileImage = document.getElementById('profileImage') as HTMLImageElement;
    const imageInput = document.getElementById('imageInput') as HTMLInputElement;
    const nicknameValue = document.getElementById('nicknameValue') as HTMLSpanElement;
    const nameValue = document.getElementById('nameValue') as HTMLSpanElement;
    const nameInput = document.getElementById('nameInput') as HTMLInputElement;
    const emailValue = document.getElementById('emailValue') as HTMLSpanElement;
    const roleValue = document.getElementById('roleValue') as HTMLSpanElement;
    const passwordValue = document.getElementById('passwordValue') as HTMLSpanElement;
    const errorDiv = document.getElementById('profileError') as HTMLDivElement;
    const errorText = document.getElementById('errorText') as HTMLParagraphElement;

    // Botones
    const editImageBtn = document.getElementById('editImageBtn') as HTMLButtonElement;
    const editPasswordBtn = document.getElementById('editPasswordBtn') as HTMLButtonElement;
    const editNameBtn = document.getElementById('editNameBtn') as HTMLButtonElement;

    // Modal de contraseña
    const passwordModal = document.getElementById('passwordModal') as HTMLDivElement;
    const currentPasswordInput = document.getElementById('currentPassword') as HTMLInputElement;
    const newPasswordInput = document.getElementById('newPassword') as HTMLInputElement;
    const confirmPasswordInput = document.getElementById('confirmPassword') as HTMLInputElement;
    const savePasswordBtn = document.getElementById('savePasswordBtn') as HTMLButtonElement;
    const cancelPasswordBtn = document.getElementById('cancelPasswordBtn') as HTMLButtonElement;

    let currentProfile: UserProfile | null = null;
    let isEditingName = false;


    // Mostrar error
    function showError(message: string) {
        errorText.textContent = message;
        errorDiv.classList.remove('disabled');
        setTimeout(() => {
            errorDiv.classList.add('disabled');
        }, 5000);
    }

    // Ocultar error
    function hideError() {
        errorDiv.classList.add('disabled');
    }

    // Cargar perfil del usuario
    async function loadProfile() {
        try {
            const token = getToken();
            if (!token) {
                requireAuth();
                return;
            }
            const response = await fetch(`${API_URL}/profile`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });

            if (response.status === 401) {
                clearSession();
                requireAuth();
                return;
            }

            const data = await response.json();

            if (data.success && data.data) {
                currentProfile = data.data;
                console.log('Perfil cargado:', currentProfile);
                console.log('Roles recibidos:', data.data.roles);
                updateProfileDisplay();
            } else {
                showError(data.message || 'Error al cargar el perfil');
            }
        } catch (error) {
            console.error('Error al cargar perfil:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }
    }

    // Actualizar visualización del perfil
    function updateProfileDisplay() {
        if (!currentProfile) return;

        nicknameValue.textContent = currentProfile.nickname;
        nameValue.textContent = currentProfile.name;
        nameInput.value = currentProfile.name;
        emailValue.textContent = currentProfile.email;

        // Mostrar roles (puede haber múltiples roles)
        if (currentProfile.roles && currentProfile.roles.length > 0) {
            const roleNames = currentProfile.roles.map(role => role.name).join(', ');
            roleValue.textContent = roleNames;
        } else {
            roleValue.textContent = 'Sin rol asignado';
        }

        const imageUrl = currentProfile.image || '../public/logo.png';
        profileImage.src = imageUrl;
    }

    // Editar nombre
    editNameBtn.addEventListener('click', () => {
        if (!isEditingName) {
            // Activar modo edición
            isEditingName = true;
            nameValue.classList.add('hidden');
            nameInput.classList.remove('disabled');
            nameInput.disabled = false;
            nameInput.focus();
            editNameBtn.textContent = 'Guardar';
            editNameBtn.classList.remove('btn-primary');
            editNameBtn.classList.add('success');
        } else {
            // Guardar nombre
            saveName();
        }
    });

    // Guardar nombre
    async function saveName() {
        if (!currentProfile) return;
        
        const token = getToken();
        if (!token) {
            requireAuth();
            return;
        }

        const newName = nameInput.value.trim();

        if (!newName) {
            showError('El nombre no puede estar vacío');
            return;
        }

        if (newName.length > 255) {
            showError('El nombre no puede exceder 255 caracteres');
            return;
        }

        if (newName === currentProfile.name) {
            // No hay cambios, cancelar edición
            cancelNameEdit();
            return;
        }

        try {
            const token = getToken();
            if (!token) {
                requireAuth();
                return;
            }
            
            const response = await fetch(`${API_URL}/profile/name`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ name: newName })
            });

            const data = await response.json();

            if (data.success) {
                if (currentProfile) {
                    currentProfile.name = newName;
                }
                updateProfileDisplay();
                cancelNameEdit();
                hideError();
            } else {
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat();
                    showError(errorMessages.join(', '));
                } else {
                    showError(data.message || 'Error al actualizar el nombre');
                }
            }
        } catch (error) {
            console.error('Error al actualizar nombre:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }
    }

    // Cancelar edición de nombre
    function cancelNameEdit() {
        isEditingName = false;
        nameValue.classList.remove('hidden');
        nameInput.classList.add('disabled');
        nameInput.disabled = true;
        if (currentProfile) {
            nameInput.value = currentProfile.name;
        }
        editNameBtn.textContent = 'Editar perfil';
        editNameBtn.classList.remove('success');
        editNameBtn.classList.add('btn-primary');
    }

    // Cambiar imagen
    editImageBtn.addEventListener('click', () => {
        imageInput.click();
    });

    imageInput.addEventListener('change', async (e) => {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (!file) return;

        const token = getToken();
        if (!token) {
            requireAuth();
            return;
        }

        // Validar tipo de archivo
        if (!file.type.match('image/jpeg|image/png|image/jpg')) {
            showError('Solo se permiten imágenes JPEG, PNG o JPG');
            return;
        }

        // Validar tamaño (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showError('La imagen no debe exceder 2MB');
            return;
        }

        try {
            const token = getToken();
            if (!token) {
                requireAuth();
                return;
            }
            
            const formData = new FormData();
            formData.append('image', file);

            const response = await fetch(`${API_URL}/profile/image`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                if (currentProfile && data.data) {
                    currentProfile.image = data.data.image;
                }
                updateProfileDisplay();
                hideError();
            } else {
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat();
                    showError(errorMessages.join(', '));
                } else {
                    showError(data.message || 'Error al actualizar la imagen');
                }
            }
        } catch (error) {
            console.error('Error al actualizar imagen:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }

        // Limpiar input
        imageInput.value = '';
    });

    // Abrir modal de contraseña
    editPasswordBtn.addEventListener('click', () => {
        passwordModal.classList.remove('disabled');
        currentPasswordInput.value = '';
        newPasswordInput.value = '';
        confirmPasswordInput.value = '';
    });

    // Cerrar modal de contraseña
    cancelPasswordBtn.addEventListener('click', () => {
        passwordModal.classList.add('disabled');
        currentPasswordInput.value = '';
        newPasswordInput.value = '';
        confirmPasswordInput.value = '';
    });

    // Guardar contraseña
    savePasswordBtn.addEventListener('click', async () => {
        const token = getToken();
        if (!token) {
            requireAuth();
            return;
        }

        const currentPassword = currentPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Validaciones
        if (!currentPassword || !newPassword || !confirmPassword) {
            showError('Por favor, complete todos los campos');
            return;
        }

        if (newPassword.length < 8) {
            showError('La nueva contraseña debe tener al menos 8 caracteres');
            return;
        }

        if (newPassword !== confirmPassword) {
            showError('Las contraseñas no coinciden');
            return;
        }

        try {
            const token = getToken();
            if (!token) {
                requireAuth();
                return;
            }
            
            const response = await fetch(`${API_URL}/profile/password`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword,
                    new_password_confirmation: confirmPassword
                })
            });

            const data = await response.json();

            if (data.success) {
                passwordModal.classList.add('disabled');
                currentPasswordInput.value = '';
                newPasswordInput.value = '';
                confirmPasswordInput.value = '';
                hideError();
                alert('Contraseña actualizada correctamente');
            } else {
                if (data.errors) {
                    const errorMessages = Object.values(data.errors).flat();
                    showError(errorMessages.join(', '));
                } else {
                    showError(data.message || 'Error al actualizar la contraseña');
                }
            }
        } catch (error) {
            console.error('Error al actualizar contraseña:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }
    });

    // Cerrar modal al hacer clic fuera
    passwordModal.addEventListener('click', (e) => {
        if (e.target === passwordModal) {
            passwordModal.classList.add('disabled');
        }
    });

    // Cargar perfil al iniciar
    loadProfile();
});

