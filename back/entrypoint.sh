#!/bin/sh

echo "Esperando a que MySQL esté disponible..."
until nc -z db 3306; do
  echo "Aún no está lista la base de datos..."
  sleep 2
done

echo "Base de datos lista, ejecutando migraciones..."
php artisan migrate --force

echo "Iniciando PHP-FPM..."
exec php-fpm

