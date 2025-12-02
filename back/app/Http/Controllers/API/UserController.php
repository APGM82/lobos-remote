<?php
namespace App\Http\Controllers\API;
use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;


class UserController extends Controller
{
    /**
     * Obtener lista de usuarios con paginación
     * Acepta parámetro 'page' en la request para navegar entre páginas
     * Devuelve 5 usuarios por página
     */
    public function index(Request $request) {
        // Aplicar paginación: 5 usuarios por página
        // Laravel automáticamente obtiene el parámetro 'page' de la request
        $usersPaginated = User::paginate(5);

        // Devolver respuesta con estructura paginada
        return response()->json([
            "success" => true,
            "data" => $usersPaginated->items(),
            "pagination" => [
                "current_page" => $usersPaginated->currentPage(),
                "last_page" => $usersPaginated->lastPage(),
                "per_page" => $usersPaginated->perPage(),
                "total" => $usersPaginated->total(),
                "from" => $usersPaginated->firstItem(),
                "to" => $usersPaginated->lastItem(),
            ],
            "massage" => "Users retrieved successfully."
        ], 200);
    }

    public function show($id) {
        $user = User::find($id);

        return response()->json(["success" => true, "data" => $user], 200);
    }

    /**
     * Buscar usuarios por nickname con paginación
     * Acepta parámetro 'page' en la request para navegar entre páginas
     * Devuelve 5 usuarios por página que coincidan con el filtro
     */
    public function showByNickname(Request $request, $nickname){
        // Aplicar paginación: 5 usuarios por página
        // Laravel automáticamente obtiene el parámetro 'page' de la request
        $usersPaginated = User::where('nickname', 'like', "%$nickname%")->paginate(5);

        // Devolver respuesta con estructura paginada
        return response()->json([
            "success" => true,
            "data" => $usersPaginated->items(),
            "pagination" => [
                "current_page" => $usersPaginated->currentPage(),
                "last_page" => $usersPaginated->lastPage(),
                "per_page" => $usersPaginated->perPage(),
                "total" => $usersPaginated->total(),
                "from" => $usersPaginated->firstItem(),
                "to" => $usersPaginated->lastItem(),
            ],
            "massage" => "Users retrieved successfully."
        ], 200);
    }

    public function showByToken (Request $request) {
        $user = $request->user();

        $role = $user->roles;
        $data = [
            "user" => $user,
            "role" => $role,
        ];
        return response()->json(["success" => true, "data"=>$data], 200);
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
            $userRole = Role::where('name', 'user')->first();

            if ($userRole) {
                $user->roles()->attach($userRole->id);
            }

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
            $user->roles()->detach();
            $user->delete();
            return response()->json(["success"=>true, "message"=>"User successfully removed!"], 200);
        } catch (Exception $e) {
            return response()->json(["success"=>false, "data" =>$e, "message"=>"Error in removing user!"], 400);
        }
    }

    // Obtener perfil del usuario autenticado
    public function profile(Request $request)
    {
        $user = $request->user();

        if (is_null($user)) {
            return response()->json(["success" => false, "message" => "Usuario no autenticado"], 401);
        }

        // Cargar roles del usuario con los campos necesarios
        $user->load('roles:id,name');

        // Si el usuario no tiene roles, asignar el rol 'user' por defecto
        if ($user->roles->isEmpty()) {
            $userRole = Role::where('name', 'user')->first();
            if ($userRole) {
                $user->roles()->attach($userRole->id);
                // Recargar roles después de asignar
                $user->load('roles:id,name');
            }
        }

        // Obtener roles formateados
        $roles = $user->roles;

        return response()->json([
            "success" => true,
            "data" => [
                "id" => $user->id,
                "name" => $user->name,
                "nickname" => $user->nickname,
                "email" => $user->email,
                "image" => $user->image,
                "roles" => $roles
            ],
            "message" => "Perfil obtenido correctamente"
        ], 200);
    }

    // Actualizar imagen de perfil
    public function updateImage(Request $request)
    {
        $rules = [
            'image' => 'required|file|image|mimes:jpeg,png,jpg|max:2048',
        ];

        $messages = [
            'required' => 'El campo :attribute es obligatorio.',
            'image' => 'El campo :attribute debe ser una imagen.',
            'mimes' => 'El campo :attribute debe ser una imagen válida (jpeg, png, jpg).',
            'max' => 'El campo :attribute no debe exceder 2MB.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        try {
            $user = $request->user();

            if (is_null($user)) {
                return response()->json(["success" => false, "message" => "Usuario no autenticado"], 401);
            }

            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file = $request->file('image');
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();
                $filename = uniqid('img_') . '_' . Str::slug($originalName) . '.' . $extension;
                $uploadedFilePath = Storage::disk('cloudinary')->putFileAs('laravel', $file, $filename);
                $url = Storage::disk('cloudinary')->url($uploadedFilePath);
                $user->image = $url;
                $user->save();

                return response()->json([
                    "success" => true,
                    "message" => "Imagen actualizada correctamente",
                    "data" => ["image" => $user->image]
                ], 200);
            }

            return response()->json(["success" => false, "message" => "Error al subir la imagen"], 400);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error al actualizar la imagen: " . $e->getMessage()
            ], 500);
        }
    }

    // Actualizar nombre del usuario
    public function updateName(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
        ];

        $messages = [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser una cadena de caracteres.',
            'max' => 'El campo :attribute no debe exceder 255 caracteres.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        try {
            $user = $request->user();

            if (is_null($user)) {
                return response()->json(["success" => false, "message" => "Usuario no autenticado"], 401);
            }

            $user->name = $request->name;
            $user->save();

            return response()->json([
                "success" => true,
                "message" => "Nombre actualizado correctamente",
                "data" => ["name" => $user->name]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error al actualizar el nombre: " . $e->getMessage()
            ], 500);
        }
    }

    // Actualizar contraseña del usuario
    public function updatePassword(Request $request)
    {
        $rules = [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'new_password_confirmation' => 'required|string|same:new_password',
        ];

        $messages = [
            'required' => 'El campo :attribute es obligatorio.',
            'min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'same' => 'La confirmación de contraseña no coincide.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if($validator->fails()){
            return response()->json($validator->errors(), 422);
        }

        try {
            $user = $request->user();

            if (is_null($user)) {
                return response()->json(["success" => false, "message" => "Usuario no autenticado"], 401);
            }

            // Verificar contraseña actual
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    "success" => false,
                    "message" => "La contraseña actual es incorrecta"
                ], 422);
            }

            // Actualizar contraseña
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                "success" => true,
                "message" => "Contraseña actualizada correctamente"
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                "message" => "Error al actualizar la contraseña: " . $e->getMessage()
            ], 500);
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
