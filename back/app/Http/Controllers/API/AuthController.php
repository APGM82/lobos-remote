<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // Login
    public function login(Request $request)
    {
        $input = $request->all();
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:4'
        ];
        
        $validator = Validator::make($input, $rules);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        
        if($user && $user->password === md5($request->password)){
            
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
        $tokensDeleted = $user->tokens()->delete();
        
        return response()->json([
            "success" => true,
            "message" => "Tokens revocados: " . $tokensDeleted
        ]);
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
