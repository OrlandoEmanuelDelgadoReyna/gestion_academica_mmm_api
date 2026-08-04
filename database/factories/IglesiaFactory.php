<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Iglesia;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Iglesia> */
class IglesiaFactory extends Factory
{
    protected $model = Iglesia::class;

    public function definition(): array
    {
        return ['codigo' => strtoupper(fake()->unique()->bothify('IGL-###')), 'nombre' => 'Iglesia '.fake()->company(), 'direccion' => fake()->address(), 'telefono' => fake()->phoneNumber(), 'correo_electronico' => fake()->unique()->safeEmail(), 'activo' => true];
    }
}
