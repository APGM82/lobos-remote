# Imagen base con PHP
FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git unzip curl libonig-dev libzip-dev zip netcat-openbsd \
    && docker-php-ext-install pdo pdo_mysql mbstring zip pcntl

# Instalar Node.js y npm para dependencias de broadcasting (opcional, pero elimina warnings)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Crear directorio de la app
WORKDIR /var/www/html

# Copiar archivos de Laravel
COPY . .

# Establecer variable de entorno para evitar problemas con drivers de broadcasting no instalados
ENV BROADCAST_CONNECTION=null

# Instalar dependencias de PHP
# Si existe composer.lock, usarlo; si no, instalar y generar uno
# Usar --no-scripts para evitar que se ejecuten scripts que requieren Laravel configurado
# No usar --no-dev para mantener consistencia con el entrypoint que instala todas las dependencias
RUN if [ -f composer.lock ]; then \
        composer install --optimize-autoloader --no-scripts; \
    else \
        composer install --optimize-autoloader --no-scripts && \
        composer update --optimize-autoloader --no-scripts; \
    fi

# Copiar .env.example a .env si no existe .env (para poder ejecutar migraciones)
RUN if [ ! -f ".env" ]; then \
        if [ -f ".env.example" ]; then \
            cp .env.example .env; \
        fi; \
    fi

# Generar APP_KEY si no existe
RUN if ! grep -q "APP_KEY=base64:" .env 2>/dev/null; then \
        php artisan key:generate --force || true; \
    fi

# Nota: Las migraciones se ejecutarán en el entrypoint cuando el contenedor se inicie
# porque necesitan que la base de datos esté disponible

# Dar permisos de ejecución al entrypoint (compatible con Windows)

COPY entrypoint.sh /entrypoint.sh
COPY entrypoint-reverb.sh /entrypoint-reverb.sh
RUN chmod +x /entrypoint.sh /entrypoint-reverb.sh && sed -i 's/\r$//' /entrypoint.sh && sed -i 's/\r$//' /entrypoint-reverb.sh

# Exponer puertos
EXPOSE 8000 8080

# Usar sh explícitamente para evitar problemas de permisos 
ENTRYPOINT ["sh", "entrypoint.sh"]

# Descomentar para windows 
# ENTRYPOINT ["./entrypoint.sh"]

