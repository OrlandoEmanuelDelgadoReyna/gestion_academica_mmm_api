<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\EntregaTarea;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class CalificacionEntregaTareaApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_admin_can_grade_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'retroalimentacion' => 'Buen trabajo...',
        ])
            ->assertOk()
            ->assertJsonPath('data.nota', '8.00')
            ->assertJsonPath('data.retroalimentacion', 'Buen trabajo...')
            ->assertJsonPath('data.calificador.id', $admin->id)
            ->assertJsonMissingPath('data.ruta_archivo');

        $this->assertNotNull($ctx['entrega']->fresh()->calificado_at);
        $this->assertSame($admin->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
    }

    public function test_assigned_docente_can_grade_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true, puntajeMaximo: 10);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8.5,
        ])->assertOk()->assertJsonPath('data.nota', '8.50');

        $this->assertSame($ctx['docente']->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
    }

    public function test_unassigned_docente_cannot_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $ajeno = $this->createDocenteUser('docente.cal.x');

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
        ])->assertForbidden();

        $this->assertNull($ctx['entrega']->fresh()->nota);
        $this->assertNotSame($ajeno->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
    }

    public function test_alumno_cannot_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        Sanctum::actingAs($ctx['alumno']);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 10,
        ])->assertForbidden();

        $this->assertNull($ctx['entrega']->fresh()->nota);
    }

    public function test_valid_grade_returns_200(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
        ])->assertOk();
    }

    public function test_zero_grade_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 0,
        ])->assertOk()->assertJsonPath('data.nota', '0.00');
    }

    public function test_grade_equal_to_puntaje_maximo_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 10,
        ])->assertOk()->assertJsonPath('data.nota', '10.00');
    }

    public function test_grade_above_puntaje_maximo_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 10.01,
        ])->assertUnprocessable();

        $this->assertNull($ctx['entrega']->fresh()->nota);
    }

    public function test_negative_grade_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => -1,
        ])->assertUnprocessable();
    }

    public function test_decimal_grade_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8.5,
        ])->assertOk()->assertJsonPath('data.nota', '8.50');
    }

    public function test_saves_feedback(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'retroalimentacion' => 'Buen trabajo...',
        ])->assertOk();

        $this->assertSame('Buen trabajo...', $ctx['entrega']->fresh()->retroalimentacion);
    }

    public function test_stores_authenticated_user_as_grader(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true, puntajeMaximo: 10);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 7,
            'calificado_por_usuario_id' => $admin->id,
        ])->assertOk();

        $this->assertSame($ctx['docente']->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
        $this->assertNotSame($admin->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
    }

    public function test_stores_server_calificado_at(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        Carbon::setTestNow('2026-08-31 18:00:00');

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'calificado_at' => '2000-01-01T00:00:00.000Z',
        ])->assertOk();

        $fresh = $ctx['entrega']->fresh();
        $this->assertNotNull($fresh->calificado_at);
        $this->assertTrue($fresh->calificado_at->equalTo(Carbon::parse('2026-08-31 18:00:00')));
        $this->assertFalse($fresh->calificado_at->isSameDay(Carbon::parse('2000-01-01')));
        Carbon::setTestNow();
    }

    public function test_client_cannot_send_calificado_por_usuario_id(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $otro = $this->createDocenteUser('docente.cal.fake');

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'calificado_por_usuario_id' => $otro->id,
        ])->assertOk();

        $this->assertSame($admin->id, $ctx['entrega']->fresh()->calificado_por_usuario_id);
    }

    public function test_client_cannot_send_calificado_at(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'calificado_at' => '1999-12-31T23:59:59.000Z',
        ])->assertOk();

        $this->assertNotSame(
            '1999-12-31 23:59:59',
            $ctx['entrega']->fresh()->calificado_at?->format('Y-m-d H:i:s'),
        );
    }

    public function test_client_cannot_change_tarea_id(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $otra = $this->createTarea($this->createProgramacion('CAL-TAREA-X'), (int) $admin->id);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'tarea_id' => $otra->id,
        ])->assertOk();

        $this->assertSame($ctx['tarea']->id, $ctx['entrega']->fresh()->tarea_id);
    }

    public function test_client_cannot_change_matricula_id(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $otroAlumno = $this->createAlumnoUser('alumno.cal.mat');
        $otraMatricula = $this->enrollAlumno($otroAlumno, $ctx['programacion']);
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'matricula_id' => $otraMatricula->id,
        ])->assertOk();

        $this->assertSame($ctx['matricula']->id, $ctx['entrega']->fresh()->matricula_id);
    }

    public function test_docente_can_regrade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true, puntajeMaximo: 10);
        $this->gradeAs($ctx['docente'], $ctx['entrega'], 6, 'Primera');

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 9,
            'retroalimentacion' => 'Corregido',
        ])->assertOk()->assertJsonPath('data.nota', '9.00');
    }

    public function test_regrade_replaces_previous_note(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $this->gradeAs($admin, $ctx['entrega'], 5, 'Inicial');

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 9,
            'retroalimentacion' => 'Corregido',
        ])->assertOk();

        $this->assertSame('9.00', (string) $ctx['entrega']->fresh()->nota);
    }

    public function test_regrade_replaces_previous_feedback(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $this->gradeAs($admin, $ctx['entrega'], 5, 'Inicial');

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 9,
            'retroalimentacion' => 'Corregido',
        ])->assertOk();

        $this->assertSame('Corregido', $ctx['entrega']->fresh()->retroalimentacion);
    }

    public function test_alumno_can_view_own_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $this->gradeAs($admin, $ctx['entrega'], 8.5, 'Buen trabajo...');

        Sanctum::actingAs($ctx['alumno']);
        $this->getJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}")
            ->assertOk()
            ->assertJsonPath('data.nota', '8.50')
            ->assertJsonPath('data.retroalimentacion', 'Buen trabajo...')
            ->assertJsonMissingPath('data.ruta_archivo');

        $this->assertNotNull($this->getJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}")->json('data.calificado_at'));
    }

    public function test_alumno_cannot_view_companion_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $this->gradeAs($admin, $ctx['entrega'], 8, 'Privado');
        $companero = $this->createAlumnoUser('alumno.cal.spy');
        $this->enrollAlumno($companero, $ctx['programacion']);

        Sanctum::actingAs($companero);
        $this->getJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}")
            ->assertForbidden();
    }

    public function test_alumno_cannot_change_grade_via_normal_put(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        Sanctum::actingAs($ctx['alumno']);
        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}", [
            'contenido' => 'Intento de nota',
            'nota' => 10,
            'retroalimentacion' => 'yo mismo',
            'calificado_por_usuario_id' => $ctx['alumno']->id,
            'calificado_at' => now()->toIso8601String(),
        ])->assertOk();

        $fresh = $ctx['entrega']->fresh();
        $this->assertSame('Intento de nota', $fresh->contenido);
        $this->assertNull($fresh->nota);
        $this->assertNull($fresh->retroalimentacion);
        $this->assertNull($fresh->calificado_at);
        $this->assertNull($fresh->calificado_por_usuario_id);
    }

    public function test_grade_endpoint_cannot_retarget_another_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);
        $otroAlumno = $this->createAlumnoUser('alumno.cal.b');
        $otraMatricula = $this->enrollAlumno($otroAlumno, $ctx['programacion']);
        $otra = $this->createEntrega($ctx['tarea'], $otraMatricula->id, 'Otra entrega');
        Sanctum::actingAs($admin);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 7,
            'id' => $otra->id,
            'entregado_at' => '2001-01-01T00:00:00.000Z',
        ])->assertOk();

        $this->assertSame('7.00', (string) $ctx['entrega']->fresh()->nota);
        $this->assertNull($otra->fresh()->nota);
        $this->assertNotSame('2001-01-01 00:00:00', $ctx['entrega']->fresh()->entregado_at?->format('Y-m-d H:i:s'));
    }

    public function test_grading_entrega_of_foreign_programacion_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->makeContext($admin, assignDocente: true);
        $ajena = $this->makeContext($admin, alumnoUsername: 'alumno.cal.ajena', codigo: 'CAL-AJENA');

        Sanctum::actingAs($propia['docente']);
        $this->putJson("/api/v1/entregas-tarea/{$ajena['entrega']->id}/calificar", [
            'nota' => 8,
        ])->assertForbidden();

        $this->assertNull($ajena['entrega']->fresh()->nota);
    }

    public function test_graded_note_is_available_to_calificacion_service(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
        ])->assertOk();

        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $ctx['programacion']->id,
            'codigo' => 'TAREAS',
            'origen' => 'tareas',
            'nombre' => 'Tareas',
            'porcentaje' => 100,
            'orden' => 1,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/matriculas/{$ctx['matricula']->id}/calificaciones/calcular")
            ->assertCreated()
            ->assertJsonPath('data.promedio_tareas', '16.00');
    }

    public function test_grading_writes_update_audit(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
            'retroalimentacion' => 'Auditoría',
        ])->assertOk();

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $admin->id,
            'accion' => 'UPDATE',
            'tabla_afectada' => 'entregas_tarea',
            'registro_id' => $ctx['entrega']->id,
        ]);

        $row = DB::table('auditorias')
            ->where('tabla_afectada', 'entregas_tarea')
            ->where('registro_id', $ctx['entrega']->id)
            ->where('accion', 'UPDATE')
            ->latest('id')
            ->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('contrasena', (string) $row->datos_despues);
        $this->assertStringNotContainsString('password', (string) $row->datos_despues);
    }

    public function test_grade_response_does_not_expose_physical_file_info(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, puntajeMaximo: 10);

        $response = $this->putJson("/api/v1/entregas-tarea/{$ctx['entrega']->id}/calificar", [
            'nota' => 8,
        ])->assertOk();

        $response->assertJsonMissingPath('data.ruta_archivo')
            ->assertJsonMissingPath('data.hash')
            ->assertJsonMissingPath('data.path')
            ->assertJsonMissingPath('data.disk');

        $payload = json_encode($response->json('data'));
        $this->assertStringNotContainsString('entregas-tarea/', (string) $payload);
    }

    /**
     * @return array{
     *     programacion: ProgramacionAcademica,
     *     tarea: Tarea,
     *     alumno: Usuario,
     *     matricula: \App\Models\Matricula,
     *     entrega: EntregaTarea,
     *     docente: Usuario|null
     * }
     */
    private function makeContext(
        Usuario $admin,
        bool $assignDocente = false,
        int|float $puntajeMaximo = 20,
        string $alumnoUsername = 'alumno.cal.ok',
        string $codigo = 'CAL-OK',
    ): array {
        $programacion = $this->createProgramacion($codigo);
        $tarea = $this->createTarea($programacion, (int) $admin->id, $puntajeMaximo);
        $alumno = $this->createAlumnoUser($alumnoUsername);
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, 'Entrega para calificar');
        $docente = null;
        if ($assignDocente) {
            $docente = $this->createDocenteUser('docente.cal.ok');
            $this->assignDocente($docente, $programacion);
        }

        Sanctum::actingAs($admin);

        return compact('programacion', 'tarea', 'alumno', 'matricula', 'entrega', 'docente');
    }

    private function gradeAs(Usuario $actor, EntregaTarea $entrega, int|float $nota, ?string $retro = null): void
    {
        Sanctum::actingAs($actor);
        $payload = ['nota' => $nota];
        if ($retro !== null) {
            $payload['retroalimentacion'] = $retro;
        }
        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}/calificar", $payload)->assertOk();
    }

    private function createEntrega(Tarea $tarea, int $matriculaId, string $contenido): EntregaTarea
    {
        return EntregaTarea::query()->create([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matriculaId,
            'contenido' => $contenido,
            'entregado_at' => now(),
        ]);
    }

    private function createTarea(ProgramacionAcademica $programacion, int $actorId, int|float $puntajeMaximo = 20): Tarea
    {
        return Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Tarea calificable',
            'descripcion' => 'Descripción',
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => $puntajeMaximo,
            'creado_por_usuario_id' => $actorId,
        ]);
    }

    private function createProgramacion(string $codigo): ProgramacionAcademica
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);

        return ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => 'A',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }
}
