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
        // Estados de partidas
        $statuses = [
            [
                'code_status' => 'CREATED',
                'name' => 'created',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'WAITING',
                'name' => 'waiting',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'IN_PROGRESS',
                'name' => 'in_progress',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'FINISHED',
                'name' => 'finished',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'CANCELLED',
                'name' => 'cancelled',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code_status' => 'DELETED',
                'name' => 'deleted',
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

