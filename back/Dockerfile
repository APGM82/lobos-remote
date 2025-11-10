# Imagen base con PHP
FROM php:8.2-fpm

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git unzip curl libonig-dev libzip-dev zip netcat-openbsd \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Crear directorio de la app
WORKDIR /var/www/html

# Copiar archivos de Laravel
COPY . .

# Instalar dependencias de PHP
RUN composer install --no-dev --optimize-autoloader

# Exponer puerto
EXPOSE 9000

ENTRYPOINT ["sh", "entrypoint.sh"]

