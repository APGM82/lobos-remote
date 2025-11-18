#!/bin/sh

# Instalar dependencias de Composer siempre para evitar problemas de compatibilidad
echo "Instalando dependencias de Composer..."
composer install --no-interaction --optimize-autoloader

# Copiar .env.example a .env si no existe .env
if [ ! -f ".env" ]; then
  if [ -f ".env.example" ]; then
    cp .env.example .env
    echo "Archivo .env creado desde .env.example"
  else
    echo "Advertencia: No se encontró .env.example, asegúrate de tener un archivo .env configurado"
  fi
fi

# Generar key de Laravel si no existe
if ! grep -q "APP_KEY=base64:" .env; then
  echo "Generando APP_KEY..."
  php artisan key:generate --force
fi

echo "Esperando a que MySQL esté disponible..."
until nc -z db 3306; do
  echo "Aún no está lista la base de datos..."
  sleep 2
done

echo "Base de datos lista, ejecutando migraciones..."
php artisan migrate --force

echo "Ejecutando seeders..."
php artisan db:seed --force

echo "Iniciando servidor de laravel puerto 8000"
exec php artisan serve --host=0.0.0.0 --port=8000
