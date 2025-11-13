<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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


        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }

        $email = $input['email'];

        $password = $this->generatePassword();

        $data = [
            "nickname" => $input['nickname'],
            "password" => $password
        ];

        $input['password'] = bcrypt($password);
        try {


            $user = User::create($input);

            Mail::send('NewUserMail', $data,function ($message) use ($email) {
                $message->to($email);
                $message->subject('Bienvenido a los hombres lobo de Castronegro');
                $message->from('los4mosqueperrosdev@gmail.com');
            });

            $success = [
                'name' => $user->name,
                'id' => $user->id
            ];

            return response()->json(["success"=>true,"data"=>$success, "message" => "User successfully registered!"], 201);
        } catch (Exception $e) {
            return response()->json(["success"=>false,"data" =>$e, "message"=>"Error in creating user!"], 400);
        }

    }

    public function destroy($id) {
        $user = User::find($id);

        if (is_null($user)) {
            return response()->json(["success"=>false, "message"=>"Error in removing user!"], 400);
        }

        try {
            $user->delete();
            return response()->json(["success"=>true, "message"=>"User successfully removed!"], 200);
        } catch (Exception $e) {
            return response()->json(["success"=>false, "data" =>$e, "message"=>"Error in removing user!"], 400);
        }
    }

    public function generatePassword($length = 8)
    {
        $upper = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $lower = "abcdefghijklmnopqrstuvwxyz";
        $num = "0123456789";
        $special = "!@#$%^&*;:,.<>?";


        $password = $upper[random_int(0, strlen($upper) - 1)];
        $password .= $lower[random_int(0, strlen($lower) - 1)];
        $password .= $num[random_int(0, strlen($num) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];


        $all = $upper . $lower . $num . $special;

        for ($i = 4; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }


        return str_shuffle($password);
    }
}
