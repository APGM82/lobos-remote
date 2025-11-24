# JS_PHP_HombresLobosCastronegro


## Puesta en marcha del proyecto

### Configurar Git Hooks (Pre-commit)

Para que los tests se ejecuten automáticamente antes de cada commit, crea el archivo de hook:

1. Crea el archivo `.git/hooks/pre-commit` con el siguiente contenido:

```bash
#!/bin/sh

echo "Ejecutando tests pre-commit..."

# Ejecutar el test dentro del contenedor de Docker
docker exec vite_app npm test

# Capturar el código de salida
if [ $? -ne 0 ]; then
  echo "Pre-commit fallido: Los tests no pasaron"
  echo "Solucion: Verifica que todas las paginas HTML requeridas existan"
  exit 1
fi

echo "Pre-commit exitoso"
exit 0
```

2. Dale permisos de ejecución (Linux/Mac):
```bash
chmod +x .git/hooks/pre-commit
```

En Windows, el archivo funcionará automáticamente si Git está configurado correctamente.

### Iniciar el entorno con Docker
Para iniciar el proyecto utilizando **Docker Compose**, ejecuta:

```bash
docker-compose up -d
```

### Detener el entorno de Docker
Para detener el proyecto utilizando **Docker Compose**, ejecuta:

```bash
docker-compose down
```

## Gestión de la base de datos (Laravel + Docker)
### Reiniciar todas las tablas

Ejecuta la migración limpia para reconstruir todas las tablas:

```
docker exec laravel_app php artisan migrate:fresh
```

### Poblar la base de datos con datos iniciales

Ejecuta los seeders para llenar las tablas:

```
docker exec laravel_app php artisan db:seed
```

## Credenciales del administrador por defecto

### El proyecto crea un usuario administrador inicial con las siguientes credenciales:

- Usuario:
```
 los4mosqueperrosdev@gmail.com
```

- Contraseña:
```
admin12345$
```



