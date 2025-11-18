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

# Eliminar composer.lock si existe (para regenerar con las versiones actuales)
RUN rm -f composer.lock

# Instalar dependencias de PHP
RUN composer install --no-dev --optimize-autoloader

# Dar permisos de ejecución al entrypoint (compatible con Windows)

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && sed -i 's/\r$//' /entrypoint.sh

# Exponer puerto
EXPOSE 8000

# Usar sh explícitamente para evitar problemas de permisos 
ENTRYPOINT ["sh", "entrypoint.sh"]

# Descomentar para windows 
# ENTRYPOINT ["./entrypoint.sh"]

