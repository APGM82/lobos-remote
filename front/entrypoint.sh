#!/bin/sh

# Instalar dependencias de npm si no existen

  echo "Instalando dependencias de npm..."
  npm install

# La configuración ahora se obtiene exclusivamente desde la API del backend
echo "Configuración: Las variables se cargarán desde la API del backend (/api/vite-config)"

echo "Iniciando servidor de desarrollo Vite con logs detallados..."
exec npm run dev -- --host 0.0.0.0

