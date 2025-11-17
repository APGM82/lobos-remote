<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class UserController extends Controller
{
    public function index() {
        $users = User::all();

        return response()->json(["success" => true, "data"=>$users, "massage" => "Users retrieved successfully."], 200);
    }

    public function show($nickname){
        $users = User::where('nickname', 'like', "%$nickname%")->get();

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

        $input['image'] = "https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png";

        $data = [
            "nickname" => $input['nickname'],
            "password" => $password
        ];

        $input['password'] = Hash::make($password);
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

    public function update(Request $request, $id) {
        $input = $request->all();


        $rules = [
            'name' => 'nullable|string|max:20',
            'image' => 'nullable|file|image|mimes:jpeg,png,jpg|max:2048',
        ];
        $messages = [
            'max' => 'El campo :attribute no debe exceder el tamaño máximo permitido.',
            'string' => 'El campo :attribute debe ser una cadena de caracteres.',
            'image' => 'El campo :attribute debe ser una imagen.',
            'mimes' => 'El campo :attribute debe ser una imagen.',

        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }

        try {
            $user = User::find($id);

            if (is_null($user)) {
                return response()->json(["success"=>false, "message"=>"User not found!"], 400);
            }

            if (isset($input['name']) && $input['name'] !== "" && $input['name'] != $user->name) {
                $user->name = $input['name'];
            }

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                try {
                    $file = $request->file('image');

                    $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();

                    $filename = uniqid('img_') . '_' . Str::slug($originalName) . '.' . $extension;

                    $uploadedFilePath = Storage::disk('cloudinary')->putFileAs('laravel', $file, $filename);

                    $url = Storage::disk('cloudinary')->url($uploadedFilePath);

                    $user->image = $url;

                } catch (Exception $e) {
                    return response()->json(['error' => 'Error al subir la imagen: ' . $e->getMessage()], 500);
                }
            }

            $user->save();

            return response()->json(["success"=>true, "message"=>"User successfully updated!"], 200);

        } catch (Exception $e) {
            return response()->json(["success"=>false, "data" =>$e, "message"=>"Error in updating user!"], 400);
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
