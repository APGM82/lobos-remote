<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener roles (deben existir, se crean en RoleSeeder)
        $adminRole = Role::where('name', 'admin')->first();
        $userRole = Role::where('name', 'user')->first();

        // Crear usuario admin solo si no existe
        $adminUser = [
            "name" => "admin",
            "nickname" => "admin",
            "email" => "los4mosqueperrosdev@gmail.com",
            "password" => Hash::make("admin12345$"),
            "image" => "https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png"
        ];

        $admin = User::firstOrCreate(
            ['nickname' => 'admin'],
            $adminUser
        );

        // Asignar rol 'admin' al usuario admin si no lo tiene
        if ($adminRole) {
            $admin->load('roles');
            if (!$admin->roles->contains($adminRole->id)) {
                $admin->roles()->attach($adminRole->id);
            }
        }

        // Crear 9 usuarios adicionales solo si no existen suficientes
        $existingUsersCount = User::where('nickname', '!=', 'admin')->count();
        $usersToCreate = max(0, 29 - $existingUsersCount);

        if ($usersToCreate > 0) {
            $newUsers = User::factory()->count($usersToCreate)->create();

            // Asignar rol 'user' a los nuevos usuarios
            if ($userRole) {
                foreach ($newUsers as $user) {
                    $user->load('roles');
                    if (!$user->roles->contains($userRole->id)) {
                        $user->roles()->attach($userRole->id);
                    }
                }
            }
        }

        // Asignar rol 'user' a usuarios existentes que no tengan roles
        if ($userRole) {
            $usersWithoutRoles = User::where('nickname', '!=', 'admin')
                ->doesntHave('roles')
                ->get();

            foreach ($usersWithoutRoles as $user) {
                $user->roles()->attach($userRole->id);
            }
        }
    }
}
