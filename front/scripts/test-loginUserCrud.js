const fetch = (...args) => import('node-fetch').then(({default: fetch}) => fetch(...args));
import fs from 'fs';

const LOGIN_URL = "http://host.docker.internal:8000/api/login";
const USERS_URL = "http://host.docker.internal:8000/api/user";
const EMAIL = "los4mosqueperrosdev@gmail.com";
const PASSWORD = "admin12345$";

(async () => {
    try {
        process.stdout.write("🔐 Iniciando sesión para obtener token...\n");
        const loginResponse = await fetch(LOGIN_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({email: EMAIL, password: PASSWORD})
        });

        const loginJson = await loginResponse.json();
        const token = loginJson.data.token;
        if (!token) {
            process.stderr.write("❌ Error: No se pudo obtener el token\n");
            process.stderr.write("Respuesta del servidor:\n" + JSON.stringify(loginJson) + "\n");
            process.stderr.write("❌ Abortando commit.\n");
            process.exit(1);
        }

        process.stdout.write(`🔑 Token obtenido correctamente: ${token}\n`);
        process.stdout.write("👥 Recuperando usuarios desde la API...\n");

        const usersResponse = await fetch(USERS_URL, {
            method: 'GET',
            headers: {'Authorization': `Bearer ${token}`}
        });
        const usersResponseJson = await usersResponse.json();

        if (usersResponse.status !== 200) {
            process.stderr.write(`❌ Error al obtener usuarios. Código: ${usersResponse.status}\n`);
            process.stderr.write("❌ Abortando commit.\n");
            process.exit(1);
        }

        fs.writeFileSync('/tmp/users_response.json', JSON.stringify(usersResponseJson));
        process.stdout.write("✅ Usuarios obtenidos correctamente (guardados en /tmp/users_response.json)\n");
        process.stdout.write("✔️ Continuando con el commit…\n");
    } catch (err) {
        process.stderr.write("❌ Error inesperado:\n" + err + "\n❌ Abortando commit.\n");
        process.exit(1);
    }
})();