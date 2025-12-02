<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\GameLobby;
use App\Models\Character;
use App\Models\Game;

echo "=== PERSONAJES DISPONIBLES ===\n";
$chars = Character::all();
foreach ($chars as $c) {
    echo "ID: {$c->id} - Nombre: {$c->name}\n";
}

echo "\n=== ÚLTIMA PARTIDA ===\n";
$lastGame = Game::orderBy('id', 'desc')->first();
echo "Game ID: {$lastGame->id}\n";

echo "\n=== JUGADORES EN ÚLTIMA PARTIDA ===\n";
$players = GameLobby::with(['user', 'character'])
    ->where('id_game', $lastGame->id)
    ->get();

$wolves = 0;
$bots = 0;
$botWolves = 0;

foreach ($players as $p) {
    $isBot = $p->user->isBot ?? 0;
    $charName = $p->character->name ?? 'NULL';
    
    if ($isBot) $bots++;
    if ($charName === 'Hombre Lobo') {
        $wolves++;
        if ($isBot) $botWolves++;
    }
    
    echo "User {$p->id_user} ({$p->user->nickname}) - Bot: {$isBot} - Char: {$charName}\n";
}

echo "\n=== RESUMEN ===\n";
echo "Total jugadores: " . count($players) . "\n";
echo "Bots: {$bots}\n";
echo "Lobos: {$wolves}\n";
echo "Lobos que son bots: {$botWolves}\n";
