<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function index() {
        $users = User::all();

        return response()->json(["success" => true, "data"=>$users, "massage" => "Users retrieved successfully."], 200);
    }

    public function store(Request $request) {
        $input = $request->all();
        $rules = [
            'name' => 'required|string|max:20',
            'email' => 'required|email|max:255|unique:users',
            'nickname' => 'required|string|max:20|unique:users',
            'password' => 'required|min:8',
            'confirm_password' => 'required|same:password',
        ];
        $messages = [
            'unique' => 'El :attribute ya está registrado en la base de datos.',
            'email' => 'El campo :attribute debe ser un correo electrónico válido.',
            'same' => 'El campo :attribute y :other deben coincidir.',
            'max' => 'El campo :attribute no debe exceder el tamaño máximo permitido.',
            'required' => 'El campo :attribute es obligatorio.'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }

        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);

        $success = [
            'name' => $user->name,
            'id' => $user->id
        ];

        return response()->json(["success"=>true,"data"=>$success, "message" => "User successfully registered!"]);

    }
}
