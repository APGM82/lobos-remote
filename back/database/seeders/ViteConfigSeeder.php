<?php

namespace Database\Seeders;

use App\Models\ViteConfig;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ViteConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Derivar valores VITE_* desde las variables REVERB_* y otras configuraciones del backend
        // Esto evita duplicar la misma información en el .env
        
        // Obtener valores desde variables de entorno (con valores por defecto)
        $reverbAppId = env('REVERB_APP_ID', 'laravel');
        $reverbAppKey = env('REVERB_APP_KEY', 'laravelKey');
        $reverbPort = env('REVERB_PORT', '8080');
        $reverbScheme = env('REVERB_SCHEME', 'http');
        $reverbCluster = env('REVERB_APP_CLUSTER', 'mt1');
        
        // Para el host, usar 'localhost' para el frontend (accede desde el navegador)
        // El backend usa 'reverb' (nombre del servicio Docker) pero el frontend usa 'localhost'
        $reverbHost = 'localhost';
        
        // Para la API, extraer host y puerto desde APP_URL o usar valores por defecto
        $appUrl = env('APP_URL', 'http://localhost:8000');
        $apiHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $apiPort = parse_url($appUrl, PHP_URL_PORT) ?: '8000';
        
        $configs = [
            [
                'key' => 'VITE_REVERB_APP_ID',
                'value' => $reverbAppId,
                'description' => 'ID de la aplicación Reverb',
                'active' => true,
            ],
            [
                'key' => 'VITE_REVERB_APP_KEY',
                'value' => $reverbAppKey,
                'description' => 'Clave de la aplicación Reverb',
                'active' => true,
            ],
            [
                'key' => 'VITE_REVERB_HOST',
                'value' => $reverbHost,
                'description' => 'Host del servidor Reverb (desde el navegador)',
                'active' => true,
            ],
            [
                'key' => 'VITE_REVERB_PORT',
                'value' => (string)$reverbPort,
                'description' => 'Puerto del servidor Reverb',
                'active' => true,
            ],
            [
                'key' => 'VITE_REVERB_SCHEME',
                'value' => $reverbScheme,
                'description' => 'Esquema del servidor Reverb (http/https)',
                'active' => true,
            ],
            [
                'key' => 'VITE_REVERB_APP_CLUSTER',
                'value' => $reverbCluster,
                'description' => 'Cluster de Reverb',
                'active' => true,
            ],
            [
                'key' => 'VITE_API_HOST',
                'value' => $apiHost,
                'description' => 'Host de la API REST (desde el navegador)',
                'active' => true,
            ],
            [
                'key' => 'VITE_API_PORT',
                'value' => (string)$apiPort,
                'description' => 'Puerto de la API REST',
                'active' => true,
            ],
        ];

        foreach ($configs as $config) {
            ViteConfig::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
