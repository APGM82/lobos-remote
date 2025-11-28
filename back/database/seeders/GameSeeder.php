<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\User;
use App\Models\StatusCode;
use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Asegurarse de que existan usuarios
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->warn('No hay usuarios en la base de datos. Creando usuarios de ejemplo...');
            $users = User::factory(10)->create();
        }

        // Excluir al usuario admin de las partidas
        $adminUser = User::where('nickname', 'admin')->first();
        $usersWithoutAdmin = $users->reject(function ($user) use ($adminUser) {
            return $adminUser && $user->id === $adminUser->id;
        });

        // Asegurarse de que existan estados
        $statuses = StatusCode::all();
        if ($statuses->isEmpty()) {
            $this->command->warn('No hay estados en la base de datos. Ejecutando StatusCodeSeeder...');
            $this->call(StatusCodeSeeder::class);
            $statuses = StatusCode::all();
        }

        // Asegurarse de que exista al menos un personaje
        $defaultCharacter = Character::first();
        if (!$defaultCharacter) {
            $this->command->warn('No hay personajes en la base de datos. Creando personaje por defecto...');
            $defaultCharacter = Character::create([
                'name' => 'Ninguno'
            ]);
        }

        // Obtener estados específicos (en español)
        $statusCreated = StatusCode::where('name', 'creada')->first();
        $statusWaiting = StatusCode::where('name', 'en_espera')->first();
        $statusInProgress = StatusCode::where('name', 'en_progreso')->first();
        $statusFinished = StatusCode::where('name', 'finalizada')->first();

        // Crear partidas de ejemplo
        $games = [
            // Partidas creadas (1-2 jugadores)
            [
                'name' => 'Partida Nocturna',
                'max_players' => 8,
                'status' => $statusCreated,
                'players_count' => 1,
            ],
            [
                'name' => 'Nueva Partida',
                'max_players' => 12,
                'status' => $statusCreated,
                'players_count' => 1,
            ],

            // Partidas esperando jugadores (3-7 jugadores)
            [
                'name' => 'Lobos de Medianoche',
                'max_players' => 12,
                'status' => $statusWaiting,
                'players_count' => 5,
            ],
            [
                'name' => 'La Aldea Secreta',
                'max_players' => 10,
                'status' => $statusWaiting,
                'players_count' => 3,
            ],
            [
                'name' => 'Cazadores del Amanecer',
                'max_players' => 8,
                'status' => $statusWaiting,
                'players_count' => 2,
            ],
            [
                'name' => 'El Misterio de Castronegro',
                'max_players' => 10,
                'status' => $statusWaiting,
                'players_count' => 7,
            ],
            [
                'name' => 'Sobrevivientes',
                'max_players' => 10,
                'status' => $statusWaiting,
                'players_count' => 4,
            ],

            // Partidas en progreso (llenas)
            [
                'name' => 'La Aldea Secreta',
                'max_players' => 15,
                'status' => $statusCreated,
                'players_count' => 15,
            ],
            [
                'name' => 'Batalla Final',
                'max_players' => 20,
                'status' => $statusCreated,
                'players_count' => 19,
            ],

            // Partidas finalizadas
            [
                'name' => 'Noche de Terror',
                'max_players' => 12,
                'status' => $statusFinished,
                'players_count' => 12,
            ],
            [
                'name' => 'Última Noche',
                'max_players' => 8,
                'status' => $statusFinished,
                'players_count' => 8,
            ],
        ];

        $this->command->info('Creando partidas de ejemplo...');

        foreach ($games as $gameData) {
            // Usar transacción para asegurar que la partida siempre tenga al menos un jugador
            DB::transaction(function () use ($gameData, $usersWithoutAdmin, $defaultCharacter) {
                // Verificar que haya usuarios disponibles (sin admin)
                if ($usersWithoutAdmin->isEmpty()) {
                    $this->command->warn('No hay usuarios disponibles (sin admin) para crear partidas.');
                    return;
                }

                // Seleccionar un usuario aleatorio como host (excluyendo admin)
                $host = $usersWithoutAdmin->random();

                // Generar código único
                $codeJoinTo = strtoupper(Str::random(6));
                while (Game::where('code_join_to', $codeJoinTo)->exists()) {
                    $codeJoinTo = strtoupper(Str::random(6));
                }

                // Crear la partida
                $game = Game::create([
                    'id_user_host' => $host->id,
                    'name' => $gameData['name'],
                    'max_players' => $gameData['max_players'],
                    'code_join_to' => $codeJoinTo,
                    'code_status' => $gameData['status']->id,
                ]);

                // Agregar jugadores a la partida
                $playersToAdd = min($gameData['players_count'], $users->count());

                // Asegurar que siempre haya al menos 1 jugador (el host)
                if ($playersToAdd < 1) {
                    $playersToAdd = 1;
                }

                $selectedPlayers = $usersWithoutAdmin->random(min($playersToAdd, $usersWithoutAdmin->count()));

                // Asegurar que el host esté siempre incluido
                if (!$selectedPlayers->contains('id', $host->id)) {
                    // Si el host no está, reemplazar uno aleatorio por el host
                    if ($selectedPlayers->count() >= $playersToAdd) {
                        $selectedPlayers = $selectedPlayers->take($playersToAdd - 1)->push($host);
                    } else {
                        $selectedPlayers->push($host);
                    }
                }

                // Agregar todos los jugadores seleccionados
                foreach ($selectedPlayers as $player) {
                    // Verificar que no esté ya agregado (por si hay duplicados)
                    $alreadyAdded = GameLobby::where('id_game', $game->id)
                        ->where('id_user', $player->id)
                        ->exists();

                    if (!$alreadyAdded) {
                        GameLobby::create([
                            'id_game' => $game->id,
                            'id_user' => $player->id,
                            'id_character' => $defaultCharacter->id,
                            'is_alive' => true,
                        ]);
                    }
                }

                // Verificar que la partida tenga al menos un jugador
                $finalPlayerCount = GameLobby::where('id_game', $game->id)->count();
                if ($finalPlayerCount === 0) {
                    // Si por alguna razón no tiene jugadores, agregar al host
                    GameLobby::create([
                        'id_game' => $game->id,
                        'id_user' => $host->id,
                        'id_character' => $defaultCharacter->id,
                        'is_alive' => true,
                    ]);
                    $finalPlayerCount = 1;
                }

                $this->command->info("Partida '{$game->name}' creada con {$finalPlayerCount} jugadores.");
            });
        }

        $this->command->info('Partidas de ejemplo creadas correctamente.');
    }
}

