<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Creates the initial system administrator account when missing. */
final class AdminUserSeeder extends Seeder
{
    private const USERNAME = 'admin';

    public function run(): void
    {
        if (DB::table('usuarios')->where('nombre_usuario', self::USERNAME)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $now = now();

            DB::table('iglesias')->updateOrInsert(
                ['codigo' => 'MMM-PRINCIPAL'],
                [
                    'nombre' => 'Iglesia MMM Principal',
                    'direccion' => 'Dirección institucional',
                    'telefono' => '999999999',
                    'correo_electronico' => 'contacto@mmm.local',
                    'activo' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $iglesiaId = DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

            DB::table('miembros')->updateOrInsert(
                [
                    'iglesia_id' => $iglesiaId,
                    'tipo_documento' => 'DNI',
                    'numero_documento' => '00000000',
                ],
                [
                    'nombres' => 'Administrador',
                    'apellidos' => 'SIGE',
                    'fecha_nacimiento' => '1990-01-01',
                    'sexo' => 'M',
                    'correo_electronico' => 'admin@mmm.local',
                    'telefono' => '999999999',
                    'direccion' => 'Dirección institucional',
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $miembroId = DB::table('miembros')
                ->where([
                    'iglesia_id' => $iglesiaId,
                    'tipo_documento' => 'DNI',
                    'numero_documento' => '00000000',
                ])
                ->value('id');

            DB::table('usuarios')->insert([
                'miembro_id' => $miembroId,
                'nombre_usuario' => self::USERNAME,
                'contrasena' => Hash::make('123456'),
                'activo' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $usuarioId = DB::table('usuarios')
                ->where('nombre_usuario', self::USERNAME)
                ->value('id');

            $adminRoleId = DB::table('roles')->where('codigo', 'ADMINISTRADOR')->value('id');

            if ($adminRoleId !== null) {
                DB::table('usuario_roles')->updateOrInsert(
                    [
                        'usuario_id' => $usuarioId,
                        'rol_id' => $adminRoleId,
                    ],
                    [
                        'asignado_por_usuario_id' => $usuarioId,
                        'asignado_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        });
    }
}
