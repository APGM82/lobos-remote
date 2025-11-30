<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ViteConfig;
use Illuminate\Http\Request;

class ViteConfigController extends Controller
{
    /**
     * Obtener todas las configuraciones activas de Vite
     * Ruta pública para que el frontend pueda leer la configuración
     */
    public function index()
    {
        $config = ViteConfig::getActiveConfig();
        
        return response()->json([
            'status' => 'ok',
            'config' => $config
        ]);
    }
}
