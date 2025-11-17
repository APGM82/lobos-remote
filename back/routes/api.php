<?php

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

// Login (sin autenticación)
Route::post('login', [AuthController::class, 'login']);

// Rutas que requieren autenticación
Route::middleware(['auth:sanctum'])->group(function () {
    // Logout
    Route::post('logout', [AuthController::class, 'logout']);
    
    // Rutas de usuarios (requiere autenticación)
    Route::middleware(['user'])->group(function () {
       //aqui por si se necesita alguna ruta
    });
    
    // Rutas solo para admins
    Route::middleware(['admin'])->group(function () {
        Route::get('/user', [UserController::class, 'index']);
        Route::post('/user/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::post('/user', [UserController::class, 'store']);
        Route::delete('/user/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    });
});
