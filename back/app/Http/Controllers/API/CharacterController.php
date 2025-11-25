<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Character;
use App\Models\Game;
use App\Models\GameLobby;
use App\Models\StatusCode;
use Illuminate\Http\Request;

class CharacterController extends Controller
{
    public function assignCharactersToUser($idGame)
    {
        $game = Game::where('id_game', $idGame)->first();
        if (!$game) {
            return response()->json([
                'success' => false,
                'message' => 'La partida no existe'
            ], 404);
        }

        if ($game->status_id != "waiting") {
            return response()->json([
                'success' => false,
                'message' => 'La partida la partida no esta disponible'
            ], 404);
        }

        $users = GameLobby::where("id_game", $idGame)->get();

        if ($users->isEmpty()) {
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

        try {
            $totalUsers = count($users);


            $uniqueRoles = [
                'Cupido',
                'Ladron',
                'Protector',
                'Niña',
                'Bruja',
                'Vidente'
            ];


            $maxWolves = 1 + floor($totalUsers / 10);
            $shuffledUsers = $users->shuffle();

            $index = 0;


            foreach ($uniqueRoles as $roleName) {
                $user = $shuffledUsers[$index];
                $user->id_character = $characters[$roleName]->id;
                $user->save();

                $index++;
            }

            for ($n = 0; $n < $maxWolves && $index < $totalUsers; $n++) {
                $user = $shuffledUsers[$index];
                $user->id_character = $characters['Lobo']->id;
                $user->save();

                $index++;
            }

            for ($i = $index; $i < $totalUsers; $i++) {
                $user = $shuffledUsers[$i];
                $user->id_character = $characters['Aldeano']->id;
                $user->save();
            }

            $progressStatus = StatusCode::where('name', 'in_progress')->first();

            $game->status_id = $progressStatus->id;
            $game->save();

            return response()->json([
                'success' => true,
                'message' => 'Personajes asignados correctamente',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Error al asignar personajes en la base de datos",
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
