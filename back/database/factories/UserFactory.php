<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Generator as Faker;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        // Usar $this->faker si está disponible, sino crear valores aleatorios simples
        if (property_exists($this, 'faker') && $this->faker !== null) {
            $faker = $this->faker;
            return [
                'name' => $faker->name(),
                'nickname' => $faker->unique()->userName(),
                'email' => $faker->unique()->safeEmail(),
                'image' => 'https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png',
                'email_verified_at' => now(),
                'password' => static::$password ??= Hash::make('password'),
                'remember_token' => Str::random(10),
                'isBot' => 1
            ];
        }

        // Fallback: generar valores aleatorios sin Faker
        $randomId = uniqid('user_', true);
        return [
            'name' => 'User ' . Str::random(8),
            'nickname' => 'user_' . Str::random(6) . rand(100, 999),
            'email' => 'user_' . Str::random(8) . '@example.com',
            'image' => 'https://res.cloudinary.com/dkwl53odf/image/upload/v1763383386/profile_jkjkq7.png',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'isBot' => 1
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
