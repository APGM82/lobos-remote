#!/bin/sh

# Instalar dependencias de Composer si no existen
if [ ! -d "vendor" ]; then
  echo "Instalando dependencias de Composer..."
  composer install --no-dev --optimize-autoloader
fi

# Copiar .env.example a .env si no existe .env
if [ ! -f ".env" ]; then
  if [ -f ".env.example" ]; then
    cp .env.example .env
  else
    echo "Advertencia: No se encontró .env.example, asegúrate de tener un archivo .env configurado"
  fi
fi

echo "Esperando a que MySQL esté disponible..."
until nc -z db 3306; do
  echo "Aún no está lista la base de datos..."
  sleep 2
done

echo "Base de datos lista, ejecutando migraciones..."
php artisan migrate --force -vvv

echo "Iniciando servidor de laravel puerto 8000"
exec php artisan serve --host=0.0.0.0 --port=8000 -vvv

