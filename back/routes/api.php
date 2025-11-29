<?php

use App\Http\Controllers\API\CharacterController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\GamePlayController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Rutas de autenticación públicas
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Mantener rutas antiguas para compatibilidad (sin prefijo auth)
Route::post('/register', [AuthController::class, 'register']);
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

    Route::group(['prefix' => 'game'], function () {
        Route::post('assignCharacters/{idGame}', [CharacterController::class, 'assignCharactersToUser']);
        
        // Rutas para obtener jugadores y matar (gameplay demo)
        Route::get('/{id}/players', [GameController::class, 'getPlayers'])->whereNumber('id');
        Route::post('/{id}/kill', [GameController::class, 'killPlayer'])->whereNumber('id');
    });

    // Rutas del gameplay (fases, votaciones, acciones de personajes)
    Route::prefix('gameplay/{gameId}')->whereNumber('gameId')->group(function () {
        // Estado del juego
        Route::get('/state', [GamePlayController::class, 'getGameState']);
        Route::post('/start', [GamePlayController::class, 'startGame']);
        Route::post('/next-phase', [GamePlayController::class, 'nextPhase']);
        
        // Votaciones
        Route::post('/wolves/start', [GamePlayController::class, 'startWolvesVoting']);
        Route::post('/village/start', [GamePlayController::class, 'startVillageVoting']);
        Route::post('/vote', [GamePlayController::class, 'vote']);
        Route::post('/vote/resolve', [GamePlayController::class, 'resolveVoting']);
        
        // Acciones de personajes (placeholders)
        Route::post('/action/cupid', [GamePlayController::class, 'cupidAction']);
        Route::post('/action/thief', [GamePlayController::class, 'thiefAction']);
        Route::post('/action/protector', [GamePlayController::class, 'protectorAction']);
        Route::post('/action/seer', [GamePlayController::class, 'seerAction']);
        Route::post('/action/witch', [GamePlayController::class, 'witchAction']);
        Route::post('/action/hunter', [GamePlayController::class, 'hunterAction']);
    });

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
