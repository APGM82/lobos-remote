import { redirectIfAuthenticated, saveSession } from './auth.ts';

document.addEventListener('DOMContentLoaded', () => {
    // Verificar si el usuario ya está autenticado (redirigir al perfil si lo está)
    if (!redirectIfAuthenticated()) {
        return;
    }

    const registerForm = document.getElementById('registerForm') as HTMLFormElement;
    const nameInput = document.getElementById('name') as HTMLInputElement;
    const nicknameInput = document.getElementById('nickname') as HTMLInputElement;
    const emailInput = document.getElementById('email') as HTMLInputElement;
    const passwordInput = document.getElementById('password') as HTMLInputElement;
    const passwordConfirmationInput = document.getElementById('passwordConfirmation') as HTMLInputElement;
    const errorDiv = document.getElementById('registerError') as HTMLDivElement;
    const errorText = document.getElementById('errorText') as HTMLParagraphElement;

    // Función para limpiar errores de un input
    function clearInputError(input: HTMLInputElement) {
        input.classList.remove('error');
    }

    // Función para marcar un input con error
    function markInputError(input: HTMLInputElement) {
        input.classList.add('error');
    }

    // Limpiar errores cuando el usuario empiece a escribir
    [nameInput, nicknameInput, emailInput, passwordInput, passwordConfirmationInput].forEach(input => {
        input.addEventListener('input', () => {
            clearInputError(input);
            // Si es el campo de contraseña o confirmación, limpiar ambos cuando se escriba
            if (input === passwordInput || input === passwordConfirmationInput) {
                clearInputError(passwordInput);
                clearInputError(passwordConfirmationInput);
            }
        });
    });

    // Manejar el envío del formulario
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Limpiar todos los errores previos
        [nameInput, nicknameInput, emailInput, passwordInput, passwordConfirmationInput].forEach(clearInputError);
        
        const name = nameInput.value.trim();
        const nickname = nicknameInput.value.trim();
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const passwordConfirmation = passwordConfirmationInput.value;

        let hasError = false;

        // Validación básica
        if (!name) {
            markInputError(nameInput);
            hasError = true;
        }
        if (!nickname) {
            markInputError(nicknameInput);
            hasError = true;
        }
        if (!email) {
            markInputError(emailInput);
            hasError = true;
        }
        if (!password) {
            markInputError(passwordInput);
            hasError = true;
        }
        if (!passwordConfirmation) {
            markInputError(passwordConfirmationInput);
            hasError = true;
        }

        if (hasError) {
            showError('Por favor, complete todos los campos');
            return;
        }

        // Validar longitud máxima de nombre
        if (name.length > 255) {
            markInputError(nameInput);
            showError('El nombre no puede exceder 255 caracteres');
            return;
        }

        // Validar longitud máxima de nickname
        if (nickname.length > 255) {
            markInputError(nicknameInput);
            showError('El nickname no puede exceder 255 caracteres');
            return;
        }

        // Validar email
        if (!validateEmail(email)) {
            markInputError(emailInput);
            showError('Por favor, ingrese un correo electrónico válido');
            return;
        }

        // Validar longitud máxima de email
        if (email.length > 255) {
            markInputError(emailInput);
            showError('El correo electrónico no puede exceder 255 caracteres');
            return;
        }

        // Validar longitud mínima de contraseña
        if (password.length < 8) {
            markInputError(passwordInput);
            showError('La contraseña debe tener al menos 8 caracteres');
            return;
        }

        // Validar que las contraseñas coincidan
        if (password !== passwordConfirmation) {
            markInputError(passwordInput);
            markInputError(passwordConfirmationInput);
            showError('Las contraseñas no coinciden');
            return;
        }

        // Intentar registrar usuario
        try {
            const response = await fetch('http://127.0.0.1:8000/api/register', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
                    nickname: nickname,
                    email: email,
                    password: password
                })
            });

            const data = await response.json();

            if (response.ok && data.user) {
                // Guardar información del usuario en sessionStorage
                const userData = {
                    id: data.user.id,
                    name: data.user.name,
                    email: data.user.email
                };
                saveSession(data.token || '', userData);
                
                // Redirigir a la página de login
                // Nota: El usuario ya tiene el rol asignado en la BD, pero redirigimos a login
                // para que haga login y obtenga el token con los permisos correctos
                window.location.href = 'login.html';
            } else {
                // Manejar errores de validación del backend
                if (data.errors) {
                    // Mapear errores a inputs específicos
                    const errorMap: { [key: string]: HTMLInputElement } = {
                        'name': nameInput,
                        'nickname': nicknameInput,
                        'email': emailInput,
                        'password': passwordInput
                    };

                    // Marcar inputs con error
                    Object.keys(data.errors).forEach(field => {
                        if (errorMap[field]) {
                            markInputError(errorMap[field]);
                        }
                    });

                    const errorMessages = Object.values(data.errors).flat();
                    showError(errorMessages.join(', '));
                } else if (data.message) {
                    showError(data.message);
                } else {
                    showError('Error al registrar usuario. Por favor, intente nuevamente.');
                }
            }
        } catch (error) {
            console.error('Error al registrar usuario:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }
    });

    function showError(message: string) {
        errorText.textContent = message;
        errorDiv.classList.remove('disabled');
        
        // Ocultar el error después de 5 segundos
        setTimeout(() => {
            errorDiv.classList.add('disabled');
        }, 5000);
    }

    function validateEmail(email: string): boolean {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});

