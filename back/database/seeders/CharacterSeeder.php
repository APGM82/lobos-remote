<?php

namespace Database\Seeders;

use App\Models\Character;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CharacterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Character::create(['id'=> 0, 'name'=> 'Ninguno']);

        $characters = [

            'Aldeano',
            'Lobo',
            'Cupido',
            'Ladron',
            'Protector',
            'Bruja',
            'Vidente',
            'Niña'
        ];

        foreach ($characters as $character) {
            Character::create(['name' => $character]);
        }
    }

}
