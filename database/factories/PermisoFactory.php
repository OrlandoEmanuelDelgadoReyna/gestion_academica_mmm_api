<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permiso;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permiso> */
class PermisoFactory extends Factory
{
    protected $model = Permiso::class;

    public function definition(): array
    {
        return ['codigo' => strtolower(fake()->unique()->bothify('modulo.accion_???')), 'modulo' => 'general', 'nombre' => fake()->words(3, true), 'descripcion' => fake()->sentence(), 'activo' => true];
    }
}
