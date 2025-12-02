<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$gameId = $argv[1] ?? 14;

$state = Illuminate\Support\Facades\Cache::get("game_state_{$gameId}");
echo "=== ESTADO DEL JUEGO {$gameId} ===\n";
print_r($state);

echo "\n=== VOTOS ACTUALES ===\n";
if (isset($state['votes'])) {
    foreach ($state['votes'] as $voterId => $targetId) {
        echo "Jugador {$voterId} votó por {$targetId}\n";
    }
} else {
    echo "No hay votos registrados\n";
}
