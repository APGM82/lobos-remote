#!/bin/sh

# Instalar dependencias de npm si no existen
if [ ! -d "node_modules" ]; then
  echo "Instalando dependencias de npm..."
  npm install
fi

echo "Iniciando servidor de desarrollo Vite con logs detallados..."
exec npm run dev -- --host 0.0.0.0

