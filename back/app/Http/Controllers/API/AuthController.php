<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\StatusCode;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    // Register
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'nickname' => 'required|string|max:255|unique:users,nickname',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'image' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'nickname' => $validated['nickname'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'image' => $validated['image'] ?? "https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png",
        ]);

        // Asignar rol 'user' por defecto al nuevo usuario
        $userRole = Role::where('name', 'user')->first();
        if ($userRole) {
            $user->roles()->attach($userRole->id);
        }

        // Cargar roles del usuario con los campos necesarios
        $user->load('roles:id,name');

        // Obtener roles formateados (igual que en login)
        $roles = $user->roles;

        // Si no hay roles (por si acaso), asignar el rol 'user' y recargar
        if ($roles->isEmpty() && $userRole) {
            $user->roles()->attach($userRole->id);
            $user->load('roles:id,name');
            $roles = $user->roles;
        }

        // Crear permisos según sus roles
        $abilities = $this->getAbilitiesByRoles($roles);

        // Crear el token con los permisos
        $tokenResult = $user->createToken('authToken', $abilities);

        // Que expire en 24 horas
        $tokenResult->accessToken->expires_at = now()->addHours(24);
        $tokenResult->accessToken->save();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
                'email' => $user->email,
                'image' => $user->image,
            ],
            'roles' => $roles,
            'token' => $tokenResult->plainTextToken,
            'expires_at' => $tokenResult->accessToken->expires_at->toDateTimeString()
        ], 201);
    }

    // Login
    public function login(Request $request)
    {
        $input = $request->all();
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8'
        ];

        $validator = Validator::make($input, $rules);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();

        if($user && Hash::check($request->password, $user->password)){

            // Cargar roles del usuario con los campos necesarios
            $user->load('roles:id,name');

            // Obtener roles del usuario
            $roles = $user->roles;

            // Crear permisos según sus roles
            $abilities = $this->getAbilitiesByRoles($roles);

            // Crear el token con los permisos
            $tokenResult = $user->createToken('authToken', $abilities);

            // Que expire en 24 horas
            $tokenResult->accessToken->expires_at = now()->addHours(24);
            $tokenResult->accessToken->save();

            $success = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'token' => $tokenResult->plainTextToken,
                'expires_at' => $tokenResult->accessToken->expires_at->toDateTimeString()
            ];

            return response()->json(["success" => true, "data" => $success, "message" => "Usuario logueado correctamente"]);
        }
        else{
            return response()->json(["success" => false, "message" => "Credenciales incorrectas"], 401);
        }
    }

    // Logout
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Eliminar al usuario de partidas en estado "esperando" o "creada"
        $this->removeUserFromWaitingGames($user);
        
        $tokensDeleted = $user->tokens()->delete();

        return response()->json([
            "success" => true,
            "message" => "Tokens revocados: " . $tokensDeleted
        ]);
    }

    /**
     * Elimina al usuario de todas las partidas en estado "esperando" o "creada"
     * Si una partida queda sin jugadores, se elimina automáticamente
     */
    private function removeUserFromWaitingGames($user)
    {
        try {
            // Obtener estados que permiten eliminar al usuario (esperando, creada)
            $waitingStatuses = StatusCode::whereIn('name', ['en_espera', 'creada'])
                ->pluck('id')
                ->toArray();

            if (empty($waitingStatuses)) {
                return;
            }

            // Obtener todas las partidas en las que el usuario está unido y que están en estado esperando/creada
            $userLobbyGameIds = GameLobby::where('id_user', $user->id)
                ->pluck('id_game')
                ->toArray();

            if (empty($userLobbyGameIds)) {
                return;
            }

            // Obtener las partidas que están en estado esperando/creada
            $gamesToProcess = Game::whereIn('id', $userLobbyGameIds)
                ->whereIn('code_status', $waitingStatuses)
                ->pluck('id')
                ->toArray();

            if (empty($gamesToProcess)) {
                return;
            }

            // Usar transacción para asegurar consistencia
            DB::transaction(function () use ($user, $gamesToProcess) {
                // Eliminar al usuario de todas las partidas en estado esperando/creada
                foreach ($gamesToProcess as $gameId) {
                    GameLobby::where('id_game', $gameId)
                        ->where('id_user', $user->id)
                        ->delete();

                    // Verificar si la partida queda sin jugadores
                    $remainingPlayers = GameLobby::where('id_game', $gameId)->count();

                    if ($remainingPlayers === 0) {
                        // Obtener el estado "deleted" o crearlo si no existe
                        $deletedStatus = StatusCode::where('name', 'eliminada')->first();
                        if (!$deletedStatus) {
                            $deletedStatus = StatusCode::create([
                                'code_status' => 'DELETED',
                                'name' => 'deleted',
                            ]);
                        }

                        // Soft delete: cambiar el estado a "deleted"
                        Game::where('id', $gameId)->update([
                            'code_status' => $deletedStatus->id
                        ]);
                    } else {
                        // Si era el host y quedan jugadores, transferir el host al primer jugador restante
                        $game = Game::find($gameId);
                        if ($game && $game->id_user_host === $user->id) {
                            $newHost = GameLobby::where('id_game', $gameId)->first();
                            if ($newHost) {
                                $game->id_user_host = $newHost->id_user;
                                $game->save();
                            }
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            // Log del error pero no fallar el logout
            \Log::error('Error al eliminar usuario de partidas en logout: ' . $e->getMessage());
        }
    }

    // Función para asignar abilities según roles
    private function getAbilitiesByRoles($roles)
    {
        $abilities = [];

        foreach($roles as $role) {
            $roleName = $role->name;

            switch($roleName) {
                case 'admin':
                    $abilities = array_merge($abilities, ['admin', 'user', 'read', 'write', 'delete']);
                    break;
                case 'user':
                    $abilities = array_merge($abilities, ['user', 'read']);
                    break;
            }
        }

        return array_unique($abilities);
    }
}
