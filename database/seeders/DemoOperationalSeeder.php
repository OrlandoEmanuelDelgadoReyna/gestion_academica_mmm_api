<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Seeds a compact, referentially consistent operational scenario for local development. */
class DemoOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $church = DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $admin = DB::table('usuarios')->where('nombre_usuario', 'admin')->value('id');
        $member = DB::table('miembros')->where('correo_electronico', 'admin@mmm.local')->value('id');

        DB::transaction(function () use ($church, $admin, $member, $now): void {
            DB::table('cargos')->updateOrInsert(['iglesia_id' => $church, 'codigo' => 'PASTOR'], ['nombre' => 'Pastor', 'descripcion' => 'Responsable pastoral', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('sociedades')->updateOrInsert(['iglesia_id' => $church, 'codigo' => 'JOVENES'], ['nombre' => 'Sociedad de Jóvenes', 'descripcion' => 'Ministerio juvenil', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            $cargo = DB::table('cargos')->where(['iglesia_id' => $church, 'codigo' => 'PASTOR'])->value('id');
            $sociedad = DB::table('sociedades')->where(['iglesia_id' => $church, 'codigo' => 'JOVENES'])->value('id');
            DB::table('miembro_cargos')->updateOrInsert(['miembro_id' => $member, 'cargo_id' => $cargo, 'fecha_inicio' => '2020-01-01'], ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('miembro_sociedades')->updateOrInsert(['miembro_id' => $member, 'sociedad_id' => $sociedad, 'fecha_ingreso' => '2020-01-01'], ['activo' => true, 'updated_at' => $now, 'created_at' => $now]);

            DB::table('aulas')->updateOrInsert(['iglesia_id' => $church, 'codigo' => 'AULA-01'], ['nombre' => 'Aula principal', 'capacidad' => 30, 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('cursos')->updateOrInsert(['iglesia_id' => $church, 'codigo' => 'BIB-101'], ['nombre' => 'Fundamentos bíblicos', 'descripcion' => 'Curso introductorio', 'activo' => true, 'updated_at' => $now, 'created_at' => $now]);
            $course = DB::table('cursos')->where(['iglesia_id' => $church, 'codigo' => 'BIB-101'])->value('id');
            $room = DB::table('aulas')->where(['iglesia_id' => $church, 'codigo' => 'AULA-01'])->value('id');
            DB::table('programaciones_academicas')->updateOrInsert(['curso_id' => $course, 'periodo' => '2026-I', 'grupo' => 'A'], ['aula_id' => $room, 'fecha_inicio' => '2026-01-10', 'fecha_fin' => '2026-03-10', 'capacidad' => 30, 'escala_maxima' => 20, 'nota_minima_aprobatoria' => 11, 'maximo_intentos_examen' => 2, 'estado' => 'abierta', 'updated_at' => $now, 'created_at' => $now]);
            $program = DB::table('programaciones_academicas')->where(['curso_id' => $course, 'periodo' => '2026-I', 'grupo' => 'A'])->value('id');
            DB::table('programacion_docentes')->updateOrInsert(['programacion_academica_id' => $program, 'miembro_id' => $member], ['asignado_at' => $now, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('matriculas')->updateOrInsert(['programacion_academica_id' => $program, 'miembro_id' => $member], ['fecha_matricula' => $now, 'estado' => 'activa', 'updated_at' => $now, 'created_at' => $now]);

            DB::table('eventos')->updateOrInsert(['iglesia_id' => $church, 'titulo' => 'Conferencia anual'], ['descripcion' => 'Evento institucional de prueba', 'inicio_at' => '2026-08-01 09:00:00', 'fin_at' => '2026-08-01 18:00:00', 'lugar' => 'Templo principal', 'estado' => 'publicado', 'creado_por_usuario_id' => $admin, 'updated_at' => $now, 'created_at' => $now]);
            DB::table('anuncios')->updateOrInsert(['iglesia_id' => $church, 'titulo' => 'Bienvenida a SIGE-MMM'], ['contenido' => 'Datos institucionales de desarrollo.', 'estado' => 'publicado', 'publicado_at' => $now, 'creado_por_usuario_id' => $admin, 'updated_at' => $now, 'created_at' => $now]);
        });
    }
}
