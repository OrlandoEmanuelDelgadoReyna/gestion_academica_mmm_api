<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Iglesia;
use App\Models\Miembro;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Miembro> */
class MiembroFactory extends Factory
{
    protected $model = Miembro::class;

    public function definition(): array
    {
        return ['iglesia_id' => Iglesia::factory(), 'tipo_documento' => 'DNI', 'numero_documento' => fake()->unique()->numerify('########'), 'nombres' => fake()->firstName(), 'apellidos' => fake()->lastName(), 'fecha_nacimiento' => fake()->dateTimeBetween('-75 years', '-18 years')->format('Y-m-d'), 'sexo' => fake()->randomElement(['M', 'F']), 'correo_electronico' => fake()->unique()->safeEmail(), 'telefono' => fake()->phoneNumber(), 'direccion' => fake()->address()];
    }
}
