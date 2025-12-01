#!/bin/sh

# Instalar dependencias de Composer siempre para evitar problemas de compatibilidad
echo "Instalando dependencias de Composer..."
composer install --no-interaction --optimize-autoloader

# Regenerar autoloader para asegurar que esté sincronizado con las dependencias instaladas
echo "Regenerando autoloader..."
composer dump-autoload --optimize

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

echo "Esperando a que el backend esté disponible..."
until nc -z backend 8000; do
  echo "Aún no está listo el backend..."
  sleep 2
done

echo "Iniciando Laravel Reverb en el puerto 8080"
# Forzar host 0.0.0.0 para aceptar conexiones desde fuera del contenedor
# Esto es necesario porque Docker mapea el puerto al host
exec php artisan reverb:start --debug  








         
