#!/bin/sh

cp .env.example .env

echo "Esperando a que MySQL esté disponible..."
until nc -z db 3306; do
  echo "Aún no está lista la base de datos..."
  sleep 2
done

echo "Base de datos lista, ejecutando migraciones..."
php artisan migrate --force 

echo "Iniciando servidor de laravel puerto 8000"
exec php artisan serve --host=0.0.0.0 --port=8000

