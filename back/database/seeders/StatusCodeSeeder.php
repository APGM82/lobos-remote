<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StatusCode;
use Illuminate\Support\Facades\DB;

class StatusCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Estados de partidas en español
        $statuses = [
            [
                'code_status' => 'CREATED',
                'name' => 'creada',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'WAITING',
                'name' => 'en_espera',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'IN_PROGRESS',
                'name' => 'en_progreso',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'FINISHED',
                'name' => 'finalizada',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'CANCELLED',
                'name' => 'cancelada',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'DELETED',
                'name' => 'eliminada',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($statuses as $status) {
            // Usar updateOrInsert para evitar duplicados
            DB::table('game_status')->updateOrInsert(
                ['name' => $status['name']],
                $status
            );
        }

        $this->command->info('Estados de partidas creados correctamente.');
    }
}

