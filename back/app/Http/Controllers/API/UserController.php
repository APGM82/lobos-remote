<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
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
            'nickname' => 'required|string|max:20|unique:users'
        ];
        $messages = [
            'unique' => 'El :attribute ya está registrado en la base de datos.',
            'email' => 'El campo :attribute debe ser un correo electrónico válido.',
            'max' => 'El campo :attribute no debe exceder el tamaño máximo permitido.',
            'required' => 'El campo :attribute es obligatorio.'
        ];

        $input['password'] = bcrypt(12345);
        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }

        try {
            $user = User::create($input);

            $success = [
                'name' => $user->name,
                'id' => $user->id
            ];

            return response()->json(["success"=>true,"data"=>$success, "message" => "User successfully registered!"], 201);
        } catch (Exception $e) {
            return response()->json(["success"=>false,"data" =>$e, "message"=>"Error in creating user!"], 400);
        }


    }
}
