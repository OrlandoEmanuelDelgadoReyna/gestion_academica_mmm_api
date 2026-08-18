<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CriterioEvaluacion;
use App\Models\EntregaTarea;
use App\Models\ExamenFinal;
use App\Models\IntentoExamen;
use App\Models\Matricula;
use App\Models\Tarea;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class CalificacionApiTest extends TestCase
{
    use AuthenticatesApiUsers;

    public function test_calcular_returns_weighted_grade_and_pass_status(): void
    {
        $this->seedInstitutionalCatalog();
        $usuario = $this->actingAsAdmin();
        $context = $this->createAcademicContext($usuario->id);

        $response = $this->postJson("/api/v1/matriculas/{$context['matricula_id']}/calificaciones/calcular");

        $response->assertCreated()
            ->assertJsonPath('data.nota_final', '16.00')
            ->assertJsonPath('data.estado', 'aprobada')
            ->assertJsonPath('data.promedio_tareas', '16.00')
            ->assertJsonPath('data.nota_examen_final', '16.00');
    }

    /** @return array{matricula_id: int} */
    private function createAcademicContext(int $usuarioId): array
    {
        $this->seedInstitutionalCatalog();

        $church = DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $member = DB::table('miembros')->where('correo_electronico', 'admin@mmm.local')->value('id');

        DB::table('aulas')->insert(['iglesia_id' => $church, 'codigo' => 'T-AULA', 'nombre' => 'Aula test', 'capacidad' => 20, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $aula = DB::table('aulas')->where('codigo', 'T-AULA')->value('id');

        DB::table('cursos')->insert(['iglesia_id' => $church, 'codigo' => 'T-CURSO', 'nombre' => 'Curso test', 'descripcion' => null, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $curso = DB::table('cursos')->where('codigo', 'T-CURSO')->value('id');

        DB::table('programaciones_academicas')->insert([
            'curso_id' => $curso,
            'aula_id' => $aula,
            'periodo' => '2026-T',
            'grupo' => 'T',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-01',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 2,
            'estado' => 'abierta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $programacionId = DB::table('programaciones_academicas')->where('periodo', '2026-T')->value('id');

        $matricula = Matricula::query()->create([
            'programacion_academica_id' => $programacionId,
            'miembro_id' => $member,
            'fecha_matricula' => now(),
            'estado' => 'activa',
        ]);

        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacionId,
            'codigo' => 'TAREAS',
            'origen' => 'tareas',
            'nombre' => 'Tareas',
            'porcentaje' => 40,
            'orden' => 1,
        ]);
        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacionId,
            'codigo' => 'EXAMEN',
            'origen' => 'examen_final',
            'nombre' => 'Examen final',
            'porcentaje' => 60,
            'orden' => 2,
        ]);

        $tarea = Tarea::query()->create([
            'programacion_academica_id' => $programacionId,
            'titulo' => 'Tarea 1',
            'descripcion' => null,
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 10,
            'creado_por_usuario_id' => $usuarioId,
        ]);

        EntregaTarea::query()->create([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matricula->id,
            'contenido' => 'Entrega completa',
            'entregado_at' => now(),
            'nota' => 8,
            'calificado_at' => now(),
            'calificado_por_usuario_id' => $usuarioId,
        ]);

        $examen = ExamenFinal::query()->create([
            'programacion_academica_id' => $programacionId,
            'titulo' => 'Examen final',
            'descripcion' => null,
            'puntaje_maximo' => 10,
            'nota_minima_aprobatoria' => 11,
            'activo' => true,
        ]);

        IntentoExamen::query()->create([
            'examen_final_id' => $examen->id,
            'matricula_id' => $matricula->id,
            'inicio_at' => now()->subHour(),
            'fin_at' => now(),
            'estado' => 'completado',
            'puntaje_obtenido' => 8,
        ]);

        return ['matricula_id' => $matricula->id];
    }
}
