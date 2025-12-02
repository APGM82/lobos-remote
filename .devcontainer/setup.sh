#!/bin/bash

echo "🐺 Configurando Lobos de Castronegro en Codespaces..."

# Esperar a que los contenedores estén listos
echo "⏳ Esperando a que los servicios estén disponibles..."
sleep 10

# Verificar que la base de datos esté lista
echo "🗄️ Verificando conexión a la base de datos..."
until docker exec mysql_db mysqladmin ping -h localhost -u root -psecret --silent 2>/dev/null; do
  echo "  Esperando MySQL..."
  sleep 3
done
echo "✅ MySQL está listo"

# Ejecutar migraciones
echo "📦 Ejecutando migraciones de Laravel..."
docker exec laravel_app php artisan migrate --force

# Ejecutar seeders
echo "🌱 Poblando la base de datos..."
docker exec laravel_app php artisan db:seed --force

echo ""
echo "🎉 ¡Configuración completada!"
echo ""
echo "📍 URLs disponibles:"
echo "   Frontend: https://${CODESPACE_NAME}-5173.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
echo "   Backend:  https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
echo "   WebSocket: https://${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
echo ""
echo "👤 Credenciales admin:"
echo "   Email: los4mosqueperrosdev@gmail.com"
echo "   Password: admin12345$"
