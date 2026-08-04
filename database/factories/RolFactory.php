<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Rol;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Rol> */
class RolFactory extends Factory
{
    protected $model = Rol::class;

    public function definition(): array
    {
        return ['codigo' => strtoupper(fake()->unique()->bothify('ROL_???')), 'nombre' => fake()->jobTitle(), 'descripcion' => fake()->sentence(), 'activo' => true];
    }
}
