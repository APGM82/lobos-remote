# JS_PHP_HombresLobosCastronegro


## 🚀 Puesta en marcha del proyecto

### ▶️ Iniciar el entorno con Docker
Para iniciar el proyecto utilizando **Docker Compose**, ejecuta:

```bash
docker-compose up -d
```

### ⏹️ Detener el entorno de Docker
Para detener el proyecto utilizando **Docker Compose**, ejecuta:

```bash
docker-compose down
```

## 🗄️ Gestión de la base de datos (Laravel + Docker)
### 🔄 Reiniciar todas las tablas

Ejecuta la migración limpia para reconstruir todas las tablas:

```
docker exec laravel_app php artisan migrate:fresh
```

### 🌱 Poblar la base de datos con datos iniciales

Ejecuta los seeders para llenar las tablas:

```
docker exec laravel_app php artisan db:seed
```

## 🔐 Credenciales del administrador por defecto

### El proyecto crea un usuario administrador inicial con las siguientes credenciales:

- Usuario:
```
 los4mosqueperrosdev@gmail.com
```

- Contraseña:
```
admin12345$
```



