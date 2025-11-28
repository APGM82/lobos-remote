<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameLobby;
use App\Models\User;
use App\Models\StatusCode;
use App\Models\Character;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Obtener un usuario aleatorio como host (excluyendo admin), o crear uno si no existe
        $adminUser = User::where('nickname', 'admin')->first();
        $user = User::where('nickname', '!=', 'admin')
            ->inRandomOrder()
            ->first() ?? User::factory()->create();
        
        // Obtener un estado aleatorio, o usar 'waiting' por defecto
        $status = StatusCode::inRandomOrder()->first() ?? StatusCode::factory()->waiting()->create();

        $gameNames = [
            'Partida Nocturna',
            'Lobos de Medianoche',
            'La Aldea Secreta',
            'Cazadores del Amanecer',
            'Noche de Terror',
            'El Misterio de Castronegro',
            'Batalla Final',
            'Sobrevivientes',
            'Última Noche',
            'La Caza',
            'Medianoche Sangrienta',
            'Aldeanos vs Lobos',
            'La Venganza',
            'Noche Sin Luna',
            'El Protector',
        ];

        return [
            'id_user_host' => $user->id,
            'name' => $this->faker->unique()->randomElement($gameNames),
            'max_players' => $this->faker->numberBetween(4, 12),
            'code_join_to' => strtoupper(Str::random(6)),
            'code_status' => $status->id,
        ];
    }

    /**
     * Partida en estado "Creada"
     */
    public function created(): static
    {
        return $this->state(function (array $attributes) {
            $status = StatusCode::where('name', 'creada')->first() 
                ?? StatusCode::factory()->created()->create();
            
            return [
                'code_status' => $status->id,
            ];
        });
    }

    /**
     * Partida en estado "Esperando"
     */
    public function waiting(): static
    {
        return $this->state(function (array $attributes) {
            $status = StatusCode::where('name', 'en_espera')->first() 
                ?? StatusCode::factory()->waiting()->create();
            
            return [
                'code_status' => $status->id,
            ];
        });
    }

    /**
     * Partida en estado "En Progreso"
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            $status = StatusCode::where('name', 'en_progreso')->first() 
                ?? StatusCode::factory()->inProgress()->create();
            
            return [
                'code_status' => $status->id,
            ];
        });
    }

    /**
     * Partida en estado "Finalizada"
     */
    public function finished(): static
    {
        return $this->state(function (array $attributes) {
            $status = StatusCode::where('name', 'finalizada')->first() 
                ?? StatusCode::factory()->finished()->create();
            
            return [
                'code_status' => $status->id,
            ];
        });
    }

    /**
     * Partida con número específico de jugadores máximo
     */
    public function withMaxPlayers(int $maxPlayers): static
    {
        return $this->state(fn (array $attributes) => [
            'max_players' => $maxPlayers,
        ]);
    }

    /**
     * Partida con host específico
     */
    public function withHost(User $host): static
    {
        return $this->state(fn (array $attributes) => [
            'id_user_host' => $host->id,
        ]);
    }

    /**
     * Configurar el factory para que siempre agregue al host como jugador
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Game $game) {
            // Asegurar que el host siempre esté en la partida
            $hostInGame = GameLobby::where('id_game', $game->id)
                ->where('id_user', $game->id_user_host)
                ->exists();

            if (!$hostInGame) {
                // Obtener un personaje por defecto (o crear uno si no existe)
                $defaultCharacter = Character::first();
                if (!$defaultCharacter) {
                    $defaultCharacter = Character::create(['name' => 'Aldeano']);
                }

                // Agregar al host como jugador en la partida
                GameLobby::create([
                    'id_game' => $game->id,
                    'id_user' => $game->id_user_host,
                    'id_character' => $defaultCharacter->id,
                ]);
            }
        });
    }
}

