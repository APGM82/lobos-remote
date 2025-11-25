<?php

namespace Database\Factories;

use App\Models\StatusCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StatusCode>
 */
class StatusCodeFactory extends Factory
{
    protected $model = StatusCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statusNames = [
            'created',
            'waiting',
            'in_progress',
            'finished',
            'cancelled'
        ];

        $name = $this->faker->unique()->randomElement($statusNames);
        
        return [
            'code_status' => strtoupper(Str::slug($name, '_')),
            'name' => $name,
        ];
    }

    /**
     * Estado: Creada
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'CREATED',
            'name' => 'created',
        ]);
    }

    /**
     * Estado: Esperando
     */
    public function waiting(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'WAITING',
            'name' => 'waiting',
        ]);
    }

    /**
     * Estado: En Progreso
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'IN_PROGRESS',
            'name' => 'in_progress',
        ]);
    }

    /**
     * Estado: Finalizada
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'FINISHED',
            'name' => 'finished',
        ]);
    }

    /**
     * Estado: Cancelada
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'CANCELLED',
            'name' => 'cancelled',
        ]);
    }

    /**
     * Estado: Eliminada
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'code_status' => 'DELETED',
            'name' => 'deleted',
        ]);
    }
}

