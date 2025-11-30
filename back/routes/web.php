<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::get('/', function () {
    return view('welcome');
});

// Ruta de broadcasting con autenticación Sanctum
// Nota: Las rutas de broadcasting necesitan autenticación con Bearer tokens
// Por defecto, Broadcast::routes() usa el middleware 'web', pero necesitamos 'auth:sanctum'
// para aceptar tokens Bearer en lugar de solo cookies
Broadcast::routes(['middleware' => ['auth:sanctum']]);
