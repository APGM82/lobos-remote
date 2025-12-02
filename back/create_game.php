<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\StatusCode;

$host = User::where('email', 'waino06@example.com')->first();
if (!$host) {
    echo "Usuario no encontrado\n";
    exit(1);
}

$statusEsperando = StatusCode::where('name', 'en_espera')->first();
if (!$statusEsperando) {
    echo "Status 'en_espera' no encontrado\n";
    exit(1);
}

$game = Game::create([
    'name' => 'Sala de Prueba GameFlow',
    'max_players' => 15,
    'id_user_host' => $host->id,
    'code_status' => $statusEsperando->id,
    'code_join_to' => strtoupper(substr(md5(time()), 0, 6))
]);

// Agregar el host al lobby
GameLobby::create([
    'id_game' => $game->id,
    'id_user' => $host->id,
    'id_character' => 1, // "Ninguno" por ahora, se asignarán al iniciar
    'is_alive' => 1
]);

// Agregar 14 usuarios más
$users = User::where('id', '!=', $host->id)->take(14)->get();
foreach ($users as $user) {
    GameLobby::create([
        'id_game' => $game->id,
        'id_user' => $user->id,
        'id_character' => 1, // "Ninguno" por ahora
        'is_alive' => 1
    ]);
}

$count = GameLobby::where('id_game', $game->id)->count();
echo "Partida creada exitosamente!\n";
echo "ID: {$game->id}\n";
echo "Nombre: {$game->name}\n";
echo "Host: {$host->nickname} ({$host->email})\n";
echo "Jugadores: {$count}/15\n";
