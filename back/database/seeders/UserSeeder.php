<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = [
            "name" => "admin",
            "nickname" => "admin",
            "email" => "loslobosDeCastronego",
            "password" => "admin12345",
            "image" => "https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png"
        ];

        User::factory()->create($user);
        User::factory()->count(9)->create();
    }
}
