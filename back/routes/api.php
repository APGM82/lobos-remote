<?php

use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\GameController;
use App\Http\Controllers\API\GamePlayController;
use App\Http\Controllers\API\ViteConfigController;
use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas (sin autenticación)
|--------------------------------------------------------------------------
*/

// Configuración de Vite (pública para el frontend)
Route::get('/vite-config', [ViteConfigController::class, 'index']);

// Autenticación
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Compatibilidad con rutas antiguas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Debug (solo desarrollo)
Route::prefix('debug')->group(function () {
    Route::get('/users-roles', function () {
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
            'roles' => $roles->map(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
            ]),
            'users' => $users->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                ]),
                'has_roles' => $user->roles->isNotEmpty(),
            ]),
            'user_roles_table' => $userRoles,
        ], 200, [], JSON_PRETTY_PRINT);
    });
});

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (requieren autenticación)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum'])->group(function () {

    //=> Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/userToken', [UserController::class, 'showByToken']);

    //=> Perfil
    Route::prefix('profile')->group(function () {
        Route::get('/', [UserController::class, 'profile']);
        Route::post('/image', [UserController::class, 'updateImage']);
        Route::post('/name', [UserController::class, 'updateName']);
        Route::post('/password', [UserController::class, 'updatePassword']);
    });

    //=> Partidas
    Route::prefix('games')->group(function () {
        Route::get('/', [GameController::class, 'index']);
        Route::get('/active', [GameController::class, 'activeGame']);
        Route::post('/', [GameController::class, 'store']);

        Route::prefix('{gameId}')->whereNumber('gameId')->group(function () {
            Route::get('/', [GameController::class, 'show']);
            Route::post('/', [GameController::class, 'update']);
            Route::delete('/', [GameController::class, 'destroy']);
            Route::post('/join', [GameController::class, 'join']);
            Route::post('/leave', [GameController::class, 'leave']);
            Route::post('/start', [GameController::class, 'start']);
            Route::get('/players', [GameController::class, 'getPlayers']);
        });
    });

    // Rutas legacy de partidas (compatibilidad)
    Route::prefix('findGame')->group(function () {
        Route::get('/', [GameController::class, 'index']);
        Route::get('/active', [GameController::class, 'activeGame']);
        Route::post('/', [GameController::class, 'store']);

        Route::prefix('{id}')->whereNumber('id')->group(function () {
            Route::get('/', [GameController::class, 'show']);
            Route::post('/', [GameController::class, 'update']);
            Route::delete('/', [GameController::class, 'destroy']);
            Route::post('/join', [GameController::class, 'join']);
            Route::post('/leave', [GameController::class, 'leave']);
            Route::post('/start', [GameController::class, 'start']);
        });
    });

    //=> Gameplay
    Route::prefix('gameplay/{gameId}')->whereNumber('gameId')->group(function () {

        // Estado del juego
        Route::get('/state', [GamePlayController::class, 'getGameState']);
        Route::post('/start', [GamePlayController::class, 'startGame']);
        Route::post('/next-phase', [GamePlayController::class, 'nextPhase']);
        Route::post('/apply-night-deaths', [GamePlayController::class, 'applyNightDeaths']);
        Route::post('/kill', [GameController::class, 'killPlayer']);

        // Votaciones
        Route::prefix('vote')->group(function () {
            Route::post('/wolves/start', [GamePlayController::class, 'startWolvesVoting']);
            Route::post('/village/start', [GamePlayController::class, 'startVillageVoting']);
            Route::post('/', [GamePlayController::class, 'vote']);
            Route::post('/resolve', [GamePlayController::class, 'resolveVoting']);
        });

        // Acciones de personajes
        Route::prefix('action')->group(function () {
            Route::post('/cupid', [GamePlayController::class, 'cupidAction']);
            Route::post('/thief', [GamePlayController::class, 'thiefAction']);
            Route::post('/protector', [GamePlayController::class, 'protectorAction']);
            Route::post('/seer', [GamePlayController::class, 'seerAction']);
            Route::post('/witch', [GamePlayController::class, 'witchAction']);
            Route::post('/hunter', [GamePlayController::class, 'hunterAction']);
        });
    });

    //=> Chat
    Route::prefix('chat/{gameId}')->whereNumber('gameId')->group(function () {
        Route::post('/send', [ChatController::class, 'sendPublic']);
        Route::post('/send-private', [ChatController::class, 'sendPrivate']);
        Route::post('/send-group', [ChatController::class, 'sendGroup']);
        Route::get('/history', [ChatController::class, 'getHistory']);
    });

    //=> Admin
    Route::middleware(['admin'])->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{nickname}', [UserController::class, 'show']);
        Route::post('/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    });

    // Rutas legacy de usuarios (compatibilidad)
    Route::middleware(['admin'])->prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{nickname}', [UserController::class, 'show']);
        Route::post('/{id}', [UserController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [UserController::class, 'destroy'])->whereNumber('id');
    });
});
