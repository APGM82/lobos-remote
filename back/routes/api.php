<?php

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);

// Login (sin autenticación)
Route::post('login', [AuthController::class, 'login']);

// Ruta de debug - ver usuarios y sus roles (sin autenticación para facilitar el debug)
Route::get('/debug/users-roles', function () {
    $users = \App\Models\User::with('roles')->get();
    $roles = \App\Models\Role::all();
    $userRoles = \Illuminate\Support\Facades\DB::table('user_roles')->get();

    return response()->json([
        'summary' => [
            'total_users' => $users->count(),
            'total_roles' => $roles->count(),
            'total_assignments' => $userRoles->count(),
            'users_with_roles' => $users->filter(fn($u) => $u->roles->isNotEmpty())->count(),
            'users_without_roles' => $users->filter(fn($u) => $u->roles->isEmpty())->count(),
        ],
        'roles' => $roles->map(function($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
            ];
        }),
        'users' => $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'roles' => $user->roles->map(function($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                }),
                'has_roles' => $user->roles->isNotEmpty(),
            ];
        }),
        'user_roles_table' => $userRoles,
    ], 200, [], JSON_PRETTY_PRINT);
});

// Rutas que requieren autenticación
Route::middleware(['auth:sanctum'])->group(function () {
    // Logout
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('userToken', [UserController::class, 'showByToken']);

    // Rutas de perfil del usuario autenticado
    Route::get('/profile', [UserController::class, 'profile']);
    Route::post('/profile/image', [UserController::class, 'updateImage']);
    Route::post('/profile/name', [UserController::class, 'updateName']);
    Route::post('/profile/password', [UserController::class, 'updatePassword']);

    // Rutas de partidas (games)
    Route::get('/findGame', [GameController::class, 'index']);
    Route::get('/findGame/active', [GameController::class, 'activeGame']);
    Route::get('/findGame/{id}', [GameController::class, 'show'])->whereNumber('id');
    Route::post('/findGame', [GameController::class, 'store']);
    Route::post('/findGame/{id}', [GameController::class, 'update'])->whereNumber('id');
    Route::delete('/findGame/{id}', [GameController::class, 'destroy'])->whereNumber('id');
    Route::post('/findGame/{id}/join', [GameController::class, 'join'])->whereNumber('id');
    Route::post('/findGame/{id}/leave', [GameController::class, 'leave'])->whereNumber('id');
    Route::post('/findGame/{id}/start', [GameController::class, 'start'])->whereNumber('id');

    // Rutas de usuarios (requiere autenticación)
    Route::middleware(['user'])->group(function () {
       //aqui por si se necesita alguna ruta
    });

    // Rutas solo para admins
    Route::middleware(['admin'])->group(function () {
        Route::get('/user', [UserController::class, 'index']);
        Route::get('/user/{nickname}', [UserController::class, 'show']);
        Route::post('/user/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::post('/user', [UserController::class, 'store']);
        Route::delete('/user/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    });
});
