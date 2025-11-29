# Cambios en la rama feature/s02-integrar-votaciones

## Resumen
Se ha integrado la funcionalidad de votaciones del juego en el lobby. Ahora `gameLobby.html` maneja tanto la sala de espera como la partida en curso.

## Archivos modificados

### Frontend

**front/src/css/gameLobby.css**
- Añadidos estilos para las cartas de jugadores rectangulares (.player-card, .player-avatar, .player-nick, etc.)
- Añadidos estilos para mensajes del juego (.game-message, .message-system, .message-action, etc.)
- Tema oscuro con colores consistentes

**front/src/ts/gameLobby/MainLobby.ts**
- Añadida lógica de votación (handleVoteClick, renderCards con soporte para votos)
- Añadido polling del estado del juego (startGamePolling, processGameState)
- Añadido manejo de fases (handlePhaseChange, timer)
- Añadida función addGameMessage para mostrar eventos en el chat

**front/src/ts/findGame/Game.ts**
- Corregido bug del bucle infinito al cambiar de sala
- Añadido flag isChangingGame para evitar llamadas duplicadas
- handleLeaveAndJoin ahora hace join directo sin pasar por handleJoinGame

### Backend

**back/database/seeders/GameSeeder.php**
- Corregida variable undefined: $users -> $usersWithoutAdmin (línea 164)

### Docker

**front/Dockerfile**
- Cambiado base image de node:20 a nginx:alpine (workaround para problemas de red)
- Node se instala manualmente con apk (reversible cuando se solucione problemas de CLOUDFLARE - LA LIGA)

## Cómo probar

1. Levantar los contenedores: `docker-compose up -d`
2. Registrar un usuario o hacer login
3. Crear una partida o unirse a una existente
4. El lobby mostrará las cartas de jugadores
5. Cuando la partida esté en curso y sea fase de día, se podrá votar haciendo clic en las cartas

## Notas

- El chat aún no está conectado al backend (no hay endpoint de mensajes)
- La votación requiere que el backend envíe el estado del juego con phase='day'
- El botón "Empezar partida" solo aparece para el host cuando la sala está llena
