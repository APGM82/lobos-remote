<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViteConfig extends Model
{
    protected $table = 'vite_config'; // Especificar nombre de tabla (singular)

    protected $fillable = [
        'key',
        'value',
        'description',
        'active'
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Obtener todas las configuraciones activas como array asociativo
     */
    public static function getActiveConfig(): array
    {
        return static::where('active', true)
            ->pluck('value', 'key')
            ->toArray();
    }
}
