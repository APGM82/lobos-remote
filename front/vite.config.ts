import { defineConfig } from 'vite'

export default defineConfig({
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    // Habilitar logs detallados
    hmr: {
      overlay: true
    }
  },
  // Configuración de logs
  logLevel: 'info', // 'info' | 'warn' | 'error' | 'silent'
  clearScreen: false, // No limpiar la pantalla para ver todos los logs
  build: {
    // Logs detallados en build
    minify: false, // En desarrollo, no minificar para ver mejor los errores
    sourcemap: true, // Generar sourcemaps para debugging
  }
})

