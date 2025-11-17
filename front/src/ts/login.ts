document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm') as HTMLFormElement;
    const emailInput = document.getElementById('email') as HTMLInputElement;
    const passwordInput = document.getElementById('password') as HTMLInputElement;
    const errorDiv = document.getElementById('loginError') as HTMLDivElement;
    const errorText = document.getElementById('errorText') as HTMLParagraphElement;

    // Deshabilitar funcionalidades no implementadas
    document.getElementById('forgotPassword')?.addEventListener('click', (e) => {
        e.preventDefault();
        alert('Funcionalidad no disponible aún');
    });

    // Manejar el botón de crear cuenta
    document.getElementById('createAccount')?.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = 'register.html';
    });

    // Manejar el envío del formulario
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const email = emailInput.value.trim();
        const password = passwordInput.value;

        // Validación básica
        if (!email || !password) {
            showError('Por favor, complete todos los campos');
            return;
        }

        if (!validateEmail(email)) {
            showError('Por favor, ingrese un correo electrónico válido');
            return;
        }

        // Intentar hacer login
        try {
            const response = await fetch('http://localhost:8000/api/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const data = await response.json();

            if (data.success) {
                // Guardar información del usuario en sessionStorage
                sessionStorage.setItem('user', JSON.stringify(data.data.user));
                sessionStorage.setItem('token', data.data.token || '');
                
                // Redirigir según el rol del usuario
                const roles = data.data.roles;
                const isAdmin = roles.some((role: any) => role.name === 'admin');
                
                if (isAdmin) {
                    window.location.href = 'adminUser.html';
                } else {
                    window.location.href = '../../index.html';
                }
            } else {
                showError(data.message || 'Credenciales incorrectas');
            }
        } catch (error) {
            console.error('Error al hacer login:', error);
            showError('Error de conexión. Por favor, intente más tarde.');
        }
    });

    function showError(message: string) {
        errorText.textContent = message;
        errorDiv.classList.remove('disabled');
        
        // Ocultar el error después de 3 segundos
        setTimeout(() => {
            errorDiv.classList.add('disabled');
        }, 3000);
    }

    function validateEmail(email: string): boolean {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
});
