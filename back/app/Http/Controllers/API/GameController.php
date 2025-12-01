<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use App\Models\GameLobby;
use App\Models\StatusCode;
use App\Models\Character;
use App\Events\PlayerJoined;
use App\Events\PlayerLeft;
use App\Events\GameUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class GameController extends Controller
{
    /**
     * Listar todas las partidas
     * Permite filtrar por nombre
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $isAdmin = $user && $user->tokenCan('admin');

            $query = Game::with(['userHost:id,name,nickname', 'status:id,code_status,name', 'users:id,name,nickname']);

            // Filtro por estado si se proporciona (solo para admin)
            // Si hay filtro por estado, aplicarlo primero y no excluir eliminadas (ya que el filtro de estado es específico)
            $hasStatusFilter = $request->has('status') && $request->status && $isAdmin;

            if ($hasStatusFilter) {
                $statusName = $request->status;
                $statusCode = StatusCode::where('name', $statusName)->first();
                if ($statusCode) {
                    // Si hay filtro por estado, mostrar SOLO ese estado (no excluir eliminadas porque el filtro es específico)
                    $query->where('code_status', '=', $statusCode->id);
                }
            } else {
                // Excluir eliminadas y finalizadas del listado principal si NO hay filtro por estado específico
                $deletedStatus = StatusCode::where('name', 'eliminada')->first();
                $finishedStatus = StatusCode::where('name', 'finalizada')->first();
                $includeDeleted = $request->has('include_deleted') && $request->include_deleted === 'true' && $isAdmin;

                $statusesToExclude = [];

                if ($deletedStatus) {
                    if ($includeDeleted) {
                        // Si el admin solicita ver eliminadas, mostrar SOLO las eliminadas
                        $query->where('code_status', '=', $deletedStatus->id);
                    } else {
                        // Por defecto, excluir partidas eliminadas
                        $statusesToExclude[] = $deletedStatus->id;
                    }
                }

                // Excluir partidas finalizadas del listado principal
                if ($finishedStatus && !$includeDeleted) {
                    $statusesToExclude[] = $finishedStatus->id;
                }

                // Aplicar exclusión de estados si hay alguno que excluir
                if (!empty($statusesToExclude)) {
                    $query->whereNotIn('code_status', $statusesToExclude);
                }
            }

            // Filtro por nombre o código si se proporciona
            if ($request->has('name') && $request->name) {
                $searchTerm = $request->name;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('code_join_to', 'like', '%' . $searchTerm . '%');
                });
            }

            // Excluir la partida activa del usuario del listado principal (siempre, excepto cuando se filtra por estado finalizada)
            // La partida activa no debería estar finalizada, así que no hay conflicto
            if ($user) {
                $userLobbies = GameLobby::where('id_user', $user->id)->pluck('id_game');

                if ($userLobbies->isNotEmpty()) {
                    // Obtener estados que no se consideran "activos" (eliminadas, finalizadas)
                    $deletedStatus = StatusCode::where('name', 'eliminada')->first();
                    $finishedStatus = StatusCode::where('name', 'finalizada')->first();

                    $blockedStatusIds = [];
                    if ($finishedStatus) {
                        $blockedStatusIds[] = $finishedStatus->id;
                    }
                    if ($deletedStatus) {
                        $blockedStatusIds[] = $deletedStatus->id;
                    }

                    // Obtener todas las partidas activas del usuario (que no estén eliminadas o finalizadas)
                    $activeGameIds = [];
                    if (!empty($blockedStatusIds)) {
                        $activeGameIds = Game::whereIn('id', $userLobbies)
                            ->whereNotIn('code_status', $blockedStatusIds)
                            ->pluck('id')
                            ->toArray();
                    } else {
                        // Si no hay estados bloqueados, tomar todas las partidas del usuario
                        $activeGameIds = $userLobbies->toArray();
                    }

                    // Excluir todas las partidas activas del listado
                    if (!empty($activeGameIds)) {
                        $query->whereNotIn('id', $activeGameIds);
                    }
                }
            }

            // Ordenar por fecha de creación descendente (más reciente primero)
            $query->orderBy('created_at', 'desc');

            // Aplicar paginación: 5 partidas por página
            // Laravel automáticamente obtiene el parámetro 'page' de la request
            $gamesPaginated = $query->paginate(5);

            // Formatear respuesta con los datos de la página actual
            $gamesFormatted = $gamesPaginated->map(function ($game) {
                return [
                    'id' => $game->id,
                    'name' => $game->name,
                    'max_players' => $game->max_players,
                    'current_players' => $game->users->count(),
                    'status' => $game->status ? $game->status->name : 'unknown',
                    'code_status' => $game->code_status,
                    'code_join_to' => $game->code_join_to,
                    'host' => [
                        'id' => $game->userHost->id ?? null,
                        'name' => $game->userHost->name ?? null,
                        'nickname' => $game->userHost->nickname ?? null,
                    ],
                    'created_at' => $game->created_at,
                    'updated_at' => $game->updated_at,
                ];
            });

            // Devolver respuesta con estructura paginada
            return response()->json([
                'success' => true,
                'data' => $gamesFormatted,
                'pagination' => [
                    'current_page' => $gamesPaginated->currentPage(),
                    'last_page' => $gamesPaginated->lastPage(),
                    'per_page' => $gamesPaginated->perPage(),
                    'total' => $gamesPaginated->total(),
                    'from' => $gamesPaginated->firstItem(),
                    'to' => $gamesPaginated->lastItem(),
                ],
                'message' => 'Partidas obtenidas correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las partidas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalles de una partida específica
     */
    public function show($id)
    {
        try {
            $game = Game::with(['userHost:id,name,nickname,email', 'status:id,code_status,name', 'users:id,name,nickname'])
                ->find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Verificar que la partida no esté eliminada
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            $data = [
                'id' => $game->id,
                'name' => $game->name,
                'max_players' => $game->max_players,
                'current_players' => $game->users->count(),
                'status' => [
                    'id' => $game->status->id ?? null,
                    'code' => $game->status->code_status ?? null,
                    'name' => $game->status->name ?? 'unknown',
                ],
                'code_join_to' => $game->code_join_to,
                'host' => [
                    'id' => $game->userHost->id ?? null,
                    'name' => $game->userHost->name ?? null,
                    'nickname' => $game->userHost->nickname ?? null,
                    'email' => $game->userHost->email ?? null,
                ],
                'players' => $game->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'nickname' => $user->nickname,
                        'character' => $user->pivot->id_character,
                    ];
                }),
                'created_at' => $game->created_at,
                'updated_at' => $game->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Partida obtenida correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener la partida activa del usuario autenticado
     */
    public function activeGame(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            // Obtener todas las partidas donde el usuario participa
            $userLobbies = GameLobby::where('id_user', $user->id)->pluck('id_game');

            if ($userLobbies->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No hay partida activa'
                ], 200);
            }

            // Obtener estados bloqueados (eliminadas, finalizadas)
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            $finishedStatus = StatusCode::where('name', 'finalizada')->first();

            $blockedStatusIds = [];
            if ($finishedStatus) {
                $blockedStatusIds[] = $finishedStatus->id;
            }
            if ($deletedStatus) {
                $blockedStatusIds[] = $deletedStatus->id;
            }

            // Obtener la partida activa del usuario (que no esté eliminada o finalizada)
            $activeGame = null;
            if (!empty($blockedStatusIds)) {
                $activeGame = Game::with(['userHost:id,name,nickname,email', 'status:id,code_status,name', 'users:id,name,nickname'])
                    ->whereIn('id', $userLobbies)
                    ->whereNotIn('code_status', $blockedStatusIds)
                    ->first();
            } else {
                $activeGame = Game::with(['userHost:id,name,nickname,email', 'status:id,code_status,name', 'users:id,name,nickname'])
                    ->whereIn('id', $userLobbies)
                    ->first();
            }

            if (!$activeGame) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No hay partida activa'
                ], 200);
            }

            $data = [
                'id' => $activeGame->id,
                'name' => $activeGame->name,
                'max_players' => $activeGame->max_players,
                'current_players' => $activeGame->users->count(),
                'status' => [
                    'id' => $activeGame->status->id ?? null,
                    'code' => $activeGame->status->code_status ?? null,
                    'name' => $activeGame->status->name ?? 'unknown',
                ],
                'code_join_to' => $activeGame->code_join_to,
                'host' => [
                    'id' => $activeGame->userHost->id ?? null,
                    'name' => $activeGame->userHost->name ?? null,
                    'nickname' => $activeGame->userHost->nickname ?? null,
                    'email' => $activeGame->userHost->email ?? null,
                ],
                'players' => $activeGame->users->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'nickname' => $user->nickname,
                    ];
                }),
                'created_at' => $activeGame->created_at,
                'updated_at' => $activeGame->updated_at,
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Partida activa obtenida correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la partida activa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva partida
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            // Verificar si el usuario ya es host de otra partida activa
            $activeStatuses = StatusCode::whereIn('name', ['en_espera', 'en_progreso', 'en_curso'])
                ->pluck('id')
                ->toArray();

            if (!empty($activeStatuses)) {
                $existingGame = Game::where('id_user_host', $user->id)
                    ->whereIn('code_status', $activeStatuses)
                    ->first();

                if ($existingGame) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya eres host de una partida activa. Debes finalizar o transferir tu partida actual antes de crear una nueva.',
                        'current_game' => [
                            'id' => $existingGame->id,
                            'name' => $existingGame->name,
                            'status' => $existingGame->status->name ?? 'unknown'
                        ]
                    ], 422);
                }
            }

            $rules = [
                'name' => 'required|string|max:255',
                'max_players' => 'required|integer|min:15|max:30',
            ];

            $messages = [
                'name.required' => 'El nombre de la partida es obligatorio.',
                'name.string' => 'El nombre debe ser una cadena de caracteres.',
                'name.max' => 'El nombre no puede exceder 255 caracteres.',
                'max_players.required' => 'El número máximo de jugadores es obligatorio.',
                'max_players.integer' => 'El número máximo de jugadores debe ser un número entero.',
                'max_players.min' => 'El número mínimo de jugadores es 15.',
                'max_players.max' => 'El número máximo de jugadores es 30.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener el estado "en_espera" para nuevas partidas
            $status = StatusCode::where('name', 'en_espera')->first();

            // Si no existe "en_espera", intentar con "creada" como fallback
            if (!$status) {
                $status = StatusCode::where('name', 'creada')->first();
            }

            // Si tampoco existe "created", usar el primero disponible o el id 1
            if (!$status) {
                $status = StatusCode::where('id', 1)->first();
                if (!$status) {
                    $status = StatusCode::first();
                }
            }

            if (!$status) {
                // Si no existe, crear uno por defecto o usar el primero disponible
                $status = StatusCode::first();
                if (!$status) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se encontró un estado válido para la partida'
                    ], 500);
                }
            }

            // Generar código único para unirse a la partida
            $codeJoinTo = strtoupper(Str::random(6));

            // Verificar que el código sea único
            while (Game::where('code_join_to', $codeJoinTo)->exists()) {
                $codeJoinTo = strtoupper(Str::random(6));
            }

            // Obtener un personaje por defecto (o crear uno si no existe)
            $defaultCharacter = Character::first();
            if (!$defaultCharacter) {
                $defaultCharacter = Character::create(['name' => 'Aldeano']);
            }

            // Usar transacción para asegurar que la partida siempre tenga al menos un jugador
            $game = DB::transaction(function () use ($user, $request, $codeJoinTo, $status, $defaultCharacter) {
                // Crear la partida
                $game = Game::create([
                    'id_user_host' => $user->id,
                    'name' => $request->name,
                    'max_players' => $request->max_players,
                    'code_join_to' => $codeJoinTo,
                    'code_status' => $status->id,
                ]);

                // Agregar al host como jugador en la partida (obligatorio)
                GameLobby::create([
                    'id_game' => $game->id,
                    'id_user' => $user->id,
                    'id_character' => $defaultCharacter->id,
                    'is_alive' => true,
                ]);

                return $game;
            });

            // Recargar el juego desde la BD con todas las relaciones para asegurar datos actualizados
            $game = Game::with(['userHost:id,name,nickname', 'status:id,code_status,name', 'users:id,name,nickname'])
                ->find($game->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'max_players' => $game->max_players,
                    'current_players' => $game->users->count(),
                    'code_join_to' => $game->code_join_to,
                    'status' => $game->status->name ?? 'unknown',
                    'host' => [
                        'id' => $game->userHost->id,
                        'name' => $game->userHost->name,
                        'nickname' => $game->userHost->nickname,
                    ],
                    'players' => $game->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'nickname' => $user->nickname,
                        ];
                    }),
                ],
                'message' => 'Partida creada correctamente. El host se ha unido automáticamente.'
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar una partida
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $game = Game::find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Verificar que la partida no esté eliminada
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una partida eliminada'
                ], 422);
            }

            // Solo el host o un admin puede editar la partida
            $isAdmin = $user->tokenCan('admin');
            if ($game->id_user_host !== $user->id && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para editar esta partida'
                ], 403);
            }

            // No permitir editar si la partida está en progreso o finalizada
            $statusInProgress = StatusCode::whereIn('name', ['en_progreso', 'finalizada'])
                ->pluck('id')
                ->toArray();

            if (in_array($game->code_status, $statusInProgress)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una partida en progreso o finalizada'
                ], 422);
            }

            $rules = [
                'name' => 'nullable|string|max:255',
                'max_players' => 'nullable|integer|min:15|max:30',
            ];

            $messages = [
                'name.string' => 'El nombre debe ser una cadena de caracteres.',
                'name.max' => 'El nombre no puede exceder 255 caracteres.',
                'max_players.integer' => 'El número máximo de jugadores debe ser un número entero.',
                'max_players.min' => 'El número mínimo de jugadores es 15.',
                'max_players.max' => 'El número máximo de jugadores es 30.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Verificar que max_players no sea menor que los jugadores actuales
            $currentPlayers = $game->users->count();
            if ($request->has('max_players') && $request->max_players < $currentPlayers) {
                return response()->json([
                    'success' => false,
                    'message' => "El número máximo de jugadores no puede ser menor que los jugadores actuales ($currentPlayers)"
                ], 422);
            }

            if ($request->has('name')) {
                $game->name = $request->name;
            }

            if ($request->has('max_players')) {
                $game->max_players = $request->max_players;
            }

            $game->save();

            $game->load(['userHost:id,name,nickname', 'status:id,code_status,name']);

            // Disparar evento de broadcasting para notificar a los jugadores del cambio
            event(new GameUpdated($game));

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'max_players' => $game->max_players,
                    'status' => $game->status->name ?? 'unknown',
                ],
                'message' => 'Partida actualizada correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una partida (soft delete - cambia el estado a "deleted")
     */
    public function destroy($id)
    {
        try {
            $user = request()->user();
            $game = Game::with('users')->find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Verificar que la partida no esté ya eliminada
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La partida ya está eliminada'
                ], 422);
            }

            // Verificar permisos: admin puede eliminar cualquier partida, user solo si es host
            $isAdmin = $user->tokenCan('admin');
            $isHost = $game->id_user_host === $user->id;

            if (!$isAdmin && !$isHost) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para eliminar esta partida. Solo el host o un administrador puede eliminarla.'
                ], 403);
            }

            // Obtener el estado "deleted" o crearlo si no existe
            if (!$deletedStatus) {
                $deletedStatus = StatusCode::create([
                    'code_status' => 'DELETED',
                    'name' => 'deleted',
                ]);
            }

            // Soft delete: cambiar el estado a "deleted" en lugar de eliminar
            $game->code_status = $deletedStatus->id;
            $game->save();

            return response()->json([
                'success' => true,
                'message' => 'Partida eliminada correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unirse a una partida
     */
    public function join(Request $request, $id)
    {
        try {
            $user = $request->user();

            // Usar transacción para evitar race conditions
            return DB::transaction(function () use ($user, $id) {
                $game = Game::with(['users', 'status'])->find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Verificar que la partida no esté eliminada
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede unir a una partida eliminada'
                ], 422);
            }

            // Verificar que la partida no esté en progreso o finalizada
            $statusBlocked = StatusCode::whereIn('name', ['en_progreso', 'finalizada'])
                ->pluck('id')
                ->toArray();

            if (in_array($game->code_status, $statusBlocked)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede unir a una partida en progreso o finalizada'
                ], 422);
            }

            // Verificar que el usuario no esté ya en la partida (con lock para evitar race conditions)
            $alreadyJoined = GameLobby::where('id_game', $game->id)
                ->where('id_user', $user->id)
                ->lockForUpdate()
                ->exists();

            if ($alreadyJoined) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya estás unido a esta partida'
                ], 422);
            }

            // Verificar que el usuario no esté en otra partida activa
            $userLobbies = GameLobby::where('id_user', $user->id)->pluck('id_game');

            if ($userLobbies->isNotEmpty()) {
                // Obtener estados bloqueados (eliminadas, finalizadas)
                // $deletedStatus ya está definido arriba
                $finishedStatuses = StatusCode::whereIn('name', ['finalizada'])
                    ->pluck('id')
                    ->toArray();

                $blockedStatusIds = $finishedStatuses;
                if ($deletedStatus) {
                    $blockedStatusIds[] = $deletedStatus->id;
                }

                // Verificar si el usuario está en alguna partida que NO esté eliminada o finalizada
                $activeGame = Game::whereIn('id', $userLobbies)
                    ->whereNotIn('code_status', $blockedStatusIds)
                    ->with(['status:id,code_status,name'])
                    ->first();

                if ($activeGame) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ya estás unido a otra partida. Debes abandonar tu partida actual antes de unirte a una nueva.',
                        'current_game' => [
                            'id' => $activeGame->id,
                            'name' => $activeGame->name,
                            'status' => $activeGame->status ? $activeGame->status->name : 'unknown'
                        ]
                    ], 422);
                }
            }

            // Verificar que haya espacio disponible (con lock para evitar race conditions)
            $currentPlayers = GameLobby::where('id_game', $game->id)
                ->lockForUpdate()
                ->count();
            if ($currentPlayers >= $game->max_players) {
                return response()->json([
                    'success' => false,
                    'message' => 'La partida está llena'
                ], 422);
            }

            // Obtener un personaje por defecto (o crear uno si no existe)
            $defaultCharacter = Character::first();
            if (!$defaultCharacter) {
                $defaultCharacter = Character::create(['name' => 'Aldeano']);
            }

            // Agregar al usuario a la partida
            $lobbyCreated = GameLobby::create([
                'id_game' => $game->id,
                'id_user' => $user->id,
                'id_character' => $defaultCharacter->id,
                'is_alive' => true,
            ]);

            // Verificar que se creó correctamente
            if (!$lobbyCreated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al unirse a la partida'
                ], 500);
            }

            // Recargar el juego desde la BD con todas las relaciones para asegurar datos actualizados
            $game = Game::with(['userHost:id,name,nickname', 'status:id,code_status,name', 'users:id,name,nickname'])
                ->find($game->id);

            // Preparar información del jugador que se unió
            $playerInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
            ];

            // Disparar evento de broadcasting
            event(new PlayerJoined($game, $playerInfo));

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'max_players' => $game->max_players,
                    'current_players' => $game->users->count(),
                    'status' => $game->status->name ?? 'unknown',
                    'players' => $game->users->map(function ($user) {
                        return [
                            'id' => $user->id,
                            'name' => $user->name,
                            'nickname' => $user->nickname,
                        ];
                    }),
                ],
                'message' => 'Te has unido a la partida correctamente'
            ], 200);
            }, 5); // Timeout de 5 segundos para la transacción
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al unirse a la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Iniciar una partida (cambiar estado a en progreso)
     */
    public function start(Request $request, $id)
    {
        try {
            $user = $request->user();
            $game = Game::with(['users', 'status', 'userHost'])->find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede iniciar una partida eliminada'
                ], 422);
            }

            $isAdmin = $user->tokenCan('admin');
            if ($game->id_user_host !== $user->id && !$isAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para iniciar esta partida'
                ], 403);
            }

            $statusInProgress = StatusCode::where('name', 'en_progreso')->first();
            if (!$statusInProgress) {
                $statusInProgress = StatusCode::create([
                    'code_status' => 'IN_PROGRESS',
                    'name' => 'en_progreso',
                ]);
            }

            $statusFinished = StatusCode::where('name', 'finalizada')->first();
            if ($statusFinished && $game->code_status === $statusFinished->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La partida ya ha finalizado'
                ], 422);
            }

            if ($game->code_status === $statusInProgress->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La partida ya está en progreso'
                ], 422);
            }

            $currentPlayers = $game->users->count();
            if ($currentPlayers < $game->max_players) {
                $this->fillBots($id);
            }

            $game->code_status = $statusInProgress->id;
            $game->save();

            $this->assignCharactersToUser($id);

            $game->load([
                'userHost:id,name,nickname',
                'status:id,code_status,name',
                'users:id,name,nickname'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'max_players' => $game->max_players,
                    'current_players' => $game->users->count(),
                    'status' => [
                        'id' => $game->status->id ?? null,
                        'code' => $game->status->code_status ?? null,
                        'name' => $game->status->name ?? 'unknown',
                    ],
                    'players' => $game->users->map(function ($player) use ($game) {
                        return [
                            'id' => $player->id,
                            'name' => $player->name,
                            'nickname' => $player->nickname,
                            'character' => $player->pivot->id_character
                        ];
                    }),
                ],
                'message' => 'La partida ha comenzado correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Abandonar una partida
     * Si es el último jugador, elimina la partida automáticamente
     */
    public function leave(Request $request, $id)
    {
        try {
            $user = $request->user();
            $game = Game::with(['users', 'status'])->find($id);

            if (!$game) {
                return response()->json([
                    'success' => false,
                    'message' => 'Partida no encontrada'
                ], 404);
            }

            // Verificar que la partida no esté eliminada
            $deletedStatus = StatusCode::where('name', 'eliminada')->first();
            if ($deletedStatus && $game->code_status === $deletedStatus->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede abandonar una partida eliminada'
                ], 422);
            }

            // Verificar que el usuario esté en la partida
            $userInGame = GameLobby::where('id_game', $game->id)
                ->where('id_user', $user->id)
                ->first();

            if (!$userInGame) {
                return response()->json([
                    'success' => false,
                    'message' => 'No estás unido a esta partida'
                ], 422);
            }

            // Contar jugadores actuales
            $currentPlayers = $game->users->count();

            // Eliminar al usuario de la partida
            GameLobby::where('id_game', $game->id)
                ->where('id_user', $user->id)
                ->delete();

            // Verificar si quedan jugadores
            $remainingPlayers = GameLobby::where('id_game', $game->id)->count();

            // Preparar información del jugador que abandonó
            $playerInfo = [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname,
            ];

            // Si no quedan jugadores, hacer soft delete (cambiar estado a "deleted")
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
                $game->code_status = $deletedStatus->id;
                $game->save();

                // Disparar evento de broadcasting indicando que la partida fue eliminada
                event(new PlayerLeft($game, $playerInfo, true));

                return response()->json([
                    'success' => true,
                    'message' => 'Has abandonado la partida. La partida ha sido eliminada por no tener jugadores.'
                ], 200);
            }

            // Si era el host y quedan jugadores, asignar el host al primer jugador restante
            $hostTransferred = false;
            if ($game->id_user_host === $user->id && $remainingPlayers > 0) {
                $newHost = GameLobby::where('id_game', $game->id)->first();
                if ($newHost) {
                    $game->id_user_host = $newHost->id_user;
                    $game->save();
                    $hostTransferred = true;
                }
            }

            $game->load(['userHost:id,name,nickname', 'status:id,code_status,name', 'users:id,name,nickname']);

            // Disparar evento de broadcasting
            event(new PlayerLeft($game, $playerInfo, false));

            // Si se transfirió el host, notificar el cambio vía websocket
            if ($hostTransferred) {
                event(new GameUpdated($game));
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $game->id,
                    'name' => $game->name,
                    'current_players' => $remainingPlayers,
                ],
                'message' => 'Has abandonado la partida correctamente'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al abandonar la partida: ' . $e->getMessage()
            ], 500);
        }
    }

    public function assignCharactersToUser($idGame)
    {
        $game = Game::find($idGame);
        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'La partida no existe'
            ], 404);
        }

        // Asegúrate de comprobar el estado correcto (ajusta según tu modelo)
        // Aquí se asume que $game->code_status es el id del status.
        $waitingStatus = StatusCode::where('name', 'waiting')->first();
        if ($waitingStatus && $game->code_status !== $waitingStatus->id) {
            return response()->json([
                'success' => false,
                'message' => 'La partida no está en estado "waiting"'
            ], 422);
        }

        $lobbies = GameLobby::where('id_game', $idGame)->get();
        if ($lobbies->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay usuarios en la partida'
            ], 404);
        }

        $characters = Character::all()->keyBy('name');
        if ($characters->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay personajes en la base de datos'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $userIds = $lobbies->pluck('id_user')->toArray();
            $totalUsers = count($userIds);

            // Limpia asignaciones previas en la pivot para esta partida (evita duplicados/estado anterior)
            GameLobby::where('id_game', $idGame)->whereIn('id_user', $userIds)->delete();

            // Calcula número de lobos y mezcla usuarios
            $maxWolves = 1 + floor($totalUsers / 10);
            shuffle($userIds);

            $index = 0;

            // Asignar lobos
            for ($n = 0; $n < $maxWolves && $index < $totalUsers; $n++, $index++) {
                $userId = $userIds[$index];

                // Attach al pivot usando la relación del User para que Laravel maneje la pivot correctamente
                $user = User::find($userId);
                if ($user) {
                    $user->characterInGame()->attach($characters['Lobo']->id, ['id_game' => $idGame]);
                }
            }

            // El resto aldeanos
            for (; $index < $totalUsers; $index++) {
                $userId = $userIds[$index];
                $user = User::find($userId);
                if ($user) {
                    $user->characterInGame()->attach($characters['Aldeano']->id, ['id_game' => $idGame]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Personajes asignados correctamente',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al asignar personajes en la base de datos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function fillBots($idGame)
    {
    // Obtener el juego (un solo registro)
    $game = Game::findOrFail($idGame); // en vez de Game::get()->where(...)

    // Jugadores humanos ya en la partida
    $players = GameLobby::where('id_game', $idGame)->get();
    $numPlayers = $players->count();

    // Si ya está llena, no hacemos nada
    if ($numPlayers >= $game->max_players) {
        return;
    }

    // Calcular cuántos bots faltan
    $botsNeeded = $game->max_players - $numPlayers;

    // Obtener bots disponibles (limitar al número necesario)
    $bots = User::where('isBot', 1)
        ->take($botsNeeded)
        ->get();

    // Insertar en GameLobby (o usar relación many-to-many si la tienes)
    foreach ($bots as $bot) {
        GameLobby::create([
            'id_game'  => $idGame,
            'id_user'  => $bot->id,
            'id_character' => 1
        ]);
    }
}

}

