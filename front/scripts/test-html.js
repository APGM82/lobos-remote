import { existsSync } from 'fs';
import { join } from 'path';

console.log('Ejecutando tests del proyecto Lobos de Castronegro...\n');

// Test: Verificar que todas las páginas HTML principales existen
const paginasRequeridas = [
  'home.html',
  'login.html', 
  'register.html',
  'findGame.html',
  'profile.html'
];

const faltantes = [];

for (const pagina of paginasRequeridas) {
  const ruta = join('src', 'html', pagina);
  if (!existsSync(ruta)) {
    faltantes.push(pagina);
  }
}

if (faltantes.length > 0) {
  console.error('Test fallido: Faltan páginas HTML requeridas:');
  faltantes.forEach(pagina => console.error(`   - ${pagina}`));
  process.exit(1);
}

console.log('Todas las páginas HTML principales están presentes');
console.log('Tests pasados correctamente!\n');
process.exit(0);
