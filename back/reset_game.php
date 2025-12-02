<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Game;
use App\Models\StatusCode;

$gameId = $argv[1] ?? 13;

$game = Game::find($gameId);
$status = StatusCode::where('name', 'en_espera')->first();

if ($game && $status) {
    $game->code_status = $status->id;
    $game->save();
    echo "Partida {$gameId} marcada como 'en_espera'\n";
} else {
    echo "Error: No se encontró la partida o el status\n";
}
