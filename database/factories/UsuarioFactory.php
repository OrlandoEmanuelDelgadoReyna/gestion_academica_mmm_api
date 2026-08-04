<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Miembro;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<Usuario> */
class UsuarioFactory extends Factory
{
    protected $model = Usuario::class;

    public function definition(): array
    {
        return ['miembro_id' => Miembro::factory(), 'nombre_usuario' => fake()->unique()->userName(), 'contrasena' => Hash::make('Temporal123*'), 'activo' => true, 'ultimo_acceso_at' => null];
    }
}
