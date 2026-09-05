<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\ExamenFinal;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\OpcionPregunta;
use App\Models\PreguntaExamen;
use App\Models\ProgramacionAcademica;
use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class ExamenModuloApiTest extends TestCase
{
    use AuthenticatesApiUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_admin_can_create_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-1');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Evaluación 1'))
            ->assertSuccessful()
            ->assertJsonPath('data.titulo', 'Evaluación 1')
            ->assertJsonPath('data.puntaje_maximo', '20.00')
            ->assertJsonPath('data.nota_minima_aprobatoria', '14.00')
            ->assertJsonPath('data.creado_por_usuario_id', $admin->id);
    }

    public function test_admin_can_edit_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}", [
            'titulo' => 'Evaluación editada',
            'puntaje_maximo' => 20,
            'nota_minima_aprobatoria' => 11,
        ])->assertSuccessful()->assertJsonPath('data.titulo', 'Evaluación editada');
    }

    public function test_puntaje_maximo_must_be_greater_than_zero(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-3');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Cero', puntaje: 0))
            ->assertUnprocessable();
    }

    public function test_nota_minima_zero_is_valid(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-4');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Mínima cero', minima: 0))
            ->assertSuccessful()
            ->assertJsonPath('data.nota_minima_aprobatoria', '0.00');
    }

    public function test_nota_minima_greater_than_maximo_is_rejected(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-5');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Inválida', minima: 21))
            ->assertUnprocessable();
    }

    public function test_negative_nota_minima_is_rejected(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-6');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Negativa', minima: -1))
            ->assertUnprocessable();
    }

    public function test_assigned_docente_can_create_examen(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-DOC-C');
        $docente = $this->createDocenteUser('docente.ex.create');
        $this->assignDocente($docente, $programacion);

        Sanctum::actingAs($docente);
        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Examen del docente'))
            ->assertSuccessful()
            ->assertJsonPath('data.titulo', 'Examen del docente')
            ->assertJsonPath('data.creado_por_usuario_id', $docente->id);
    }

    public function test_unassigned_docente_cannot_create_examen(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-DOC-X');
        $this->createDocenteUser('docente.ex.no');

        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Docente ajeno'))
            ->assertForbidden();
    }

    public function test_alumno_cannot_create_examen(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('EX-ALU-C');
        $alumno = $this->createAlumnoUser('alumno.ex.create');
        $this->enrollAlumno($alumno, $programacion);

        Sanctum::actingAs($alumno);
        $this->postJson('/api/v1/examenes-finales', $this->examenPayload($programacion->id, 'Alumno no crea'))
            ->assertForbidden();
    }

    public function test_assigned_docente_can_edit_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}", [
            'titulo' => 'Tema editado por docente',
            'descripcion' => 'Nueva descripción',
        ])->assertSuccessful()->assertJsonPath('data.titulo', 'Tema editado por docente');
    }

    public function test_unassigned_docente_cannot_edit_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->createDocenteUser('docente.ex.edit.x');

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}", [
            'titulo' => 'Hack',
        ])->assertForbidden();
    }

    public function test_assigned_docente_can_view_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);

        Sanctum::actingAs($ctx['docente']);
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ctx['examen']->id);
    }

    public function test_unassigned_docente_cannot_view_or_grade_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);
        $ajeno = $this->createDocenteUser('docente.ex.ajeno');

        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")->assertForbidden();
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 16,
        ])->assertForbidden();
    }

    public function test_admin_can_register_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 16,
        ])->assertOk()
            ->assertJsonPath('data.resultado', 'aprobado')
            ->assertJsonPath('data.calificador.id', $admin->id);

        $this->assertSame($admin->id, NotaExamenFinal::query()->first()->calificado_por_usuario_id);
    }

    public function test_assigned_docente_can_register_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 10,
        ])->assertOk()->assertJsonPath('data.resultado', 'desaprobado');
    }

    public function test_alumno_cannot_register_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        Sanctum::actingAs($ctx['alumno']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 20,
        ])->assertForbidden();

        $this->assertDatabaseMissing('notas_examen_final', [
            'examen_final_id' => $ctx['examen']->id,
            'matricula_id' => $ctx['matricula']->id,
        ]);
    }

    public function test_grade_equal_to_puntaje_maximo_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 20,
        ])->assertOk();
    }

    public function test_grade_above_puntaje_maximo_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 20.01,
        ])->assertUnprocessable();
    }

    public function test_decimal_grade_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 13.50,
        ])->assertOk();
    }

    public function test_zero_grade_is_valid(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 0,
        ])->assertOk();
    }

    public function test_negative_grade_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => -1,
        ])->assertUnprocessable();
    }

    public function test_regrade_updates_same_row(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 18,
        ])->assertOk();

        $this->assertSame(1, NotaExamenFinal::query()->count());
        $this->assertEquals('18.00', (string) NotaExamenFinal::query()->first()->nota);
    }

    public function test_grader_comes_from_server_not_client(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 12,
            'calificado_por_usuario_id' => $admin->id,
        ])->assertOk();

        $this->assertSame($ctx['docente']->id, NotaExamenFinal::query()->first()->calificado_por_usuario_id);
    }

    public function test_calificado_at_comes_from_server(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        Carbon::setTestNow('2026-08-31 19:00:00');

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 12,
            'calificado_at' => '2000-01-01T00:00:00.000Z',
        ])->assertOk();

        $this->assertTrue(NotaExamenFinal::query()->first()->calificado_at->equalTo(Carbon::parse('2026-08-31 19:00:00')));
        Carbon::setTestNow();
    }

    public function test_grading_foreign_matricula_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $ajeno = $this->makeContext($admin, alumnoUsername: 'alumno.ex.b', codigo: 'EX-IDOR');

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ajeno['matricula']->id}", [
            'nota' => 16,
        ])->assertForbidden();
    }

    public function test_docente_cannot_grade_foreign_examen(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->makeContext($admin, assignDocente: true);
        $ajena = $this->makeContext($admin, alumnoUsername: 'alumno.ex.ajena', codigo: 'EX-AJENA');

        Sanctum::actingAs($propia['docente']);
        $this->putJson("/api/v1/examenes-finales/{$ajena['examen']->id}/notas/{$ajena['matricula']->id}", [
            'nota' => 16,
        ])->assertForbidden();
    }

    public function test_approved_result_when_nota_meets_minima(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        Sanctum::actingAs($ctx['alumno']);
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")
            ->assertOk()
            ->assertJsonPath('data.mi_resultado.resultado', 'aprobado')
            ->assertJsonPath('data.mi_resultado.puntos_faltantes', 0);
    }

    public function test_failed_result_and_missing_points(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")
            ->assertOk()
            ->assertJsonPath('data.mi_resultado.resultado', 'desaprobado')
            ->assertJsonPath('data.mi_resultado.puntos_faltantes', 3);
    }

    public function test_alumno_sees_own_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")
            ->assertOk()
            ->assertJsonPath('data.mi_resultado.nota', '11.00');
    }

    public function test_alumno_cannot_see_companion_grades(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $otro = $this->createAlumnoUser('alumno.ex.comp');
        $otraMatricula = $this->enrollAlumno($otro, $ctx['programacion']);
        $this->grade($ctx, 11);
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$otraMatricula->id}", [
            'nota' => 18,
        ])->assertOk();

        Sanctum::actingAs($ctx['alumno']);
        $show = $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}")->assertOk();
        $this->assertSame('11.00', (string) $show->json('data.mi_resultado.nota'));
        $this->assertNull($show->json('data.notas'));
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas")->assertForbidden();
    }

    public function test_failed_alumno_can_request_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);
        $this->grade($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")
            ->assertSuccessful()
            ->assertJsonPath('data.estado', 'pendiente');
    }

    public function test_approved_alumno_cannot_request_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")
            ->assertUnprocessable();
    }

    public function test_alumno_without_grade_cannot_request_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")
            ->assertUnprocessable();
    }

    public function test_duplicate_active_recovery_is_blocked(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")->assertSuccessful();
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")->assertUnprocessable();
    }

    public function test_assigned_docente_can_see_recovery_request(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);
        $this->grade($ctx, 11);
        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")->assertSuccessful();

        Sanctum::actingAs($ctx['docente']);
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperaciones")
            ->assertOk()
            ->assertJsonPath('data.0.estado', 'pendiente');
    }

    public function test_unassigned_docente_cannot_see_recovery_requests(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 11);
        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")->assertSuccessful();

        $this->createDocenteUser('docente.ex.rec.x');
        $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperaciones")->assertForbidden();
    }

    public function test_admin_can_approve_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'aprobada',
            'observacion' => 'Puede rendir',
        ])->assertOk()->assertJsonPath('data.estado', 'aprobada');
    }

    public function test_assigned_docente_can_approve_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'aprobada',
        ])->assertOk()->assertJsonPath('data.estado', 'aprobada');
    }

    public function test_alumno_cannot_approve_recovery(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($ctx['alumno']);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'aprobada',
        ])->assertForbidden();
    }

    public function test_recovery_can_be_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'rechazada',
            'observacion' => 'Fuera de plazo',
        ])->assertOk()->assertJsonPath('data.estado', 'rechazada');
    }

    public function test_recovery_request_notifies_staff(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, assignDocente: true);
        $this->grade($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")->assertSuccessful();

        $this->assertDatabaseHas('notificaciones', [
            'tipo' => 'examen',
            'titulo' => 'Solicitud de recuperación de examen',
        ]);
        $notificacionId = DB::table('notificaciones')
            ->where('tipo', 'examen')
            ->where('titulo', 'Solicitud de recuperación de examen')
            ->value('id');
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacionId,
            'usuario_id' => $ctx['docente']->id,
        ]);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacionId,
            'usuario_id' => $admin->id,
        ]);
        $contenido = (string) DB::table('notificaciones')->where('id', $notificacionId)->value('contenido');
        $this->assertStringContainsString('Evaluación 1', $contenido);
        $this->assertStringNotContainsString('contrasena', $contenido);
        $this->assertStringNotContainsString('password', $contenido);
        $this->assertStringNotContainsString('token', strtolower($contenido));
    }

    public function test_recovery_grade_keeps_original_and_uses_recuperacion_as_considerada(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'aprobada',
        ])->assertOk();

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 15,
        ])->assertOk()
            ->assertJsonPath('data.nota', '11.00')
            ->assertJsonPath('data.nota_recuperacion', '15.00')
            ->assertJsonPath('data.nota_considerada', 15)
            ->assertJsonPath('data.resultado', 'aprobado');

        $row = NotaExamenFinal::query()->first();
        $this->assertEquals('11.00', (string) $row->nota);
        $this->assertEquals('15.00', (string) $row->nota_recuperacion);
        $this->assertSame(SolicitudRecuperacionExamen::REALIZADA, $solicitud->fresh()->estado);
    }

    public function test_nota_considerada_uses_recuperacion_even_when_lower_than_ordinaria(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $solicitud = $this->requestRecovery($ctx);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/solicitudes-recuperacion-examen/{$solicitud->id}", [
            'estado' => 'aprobada',
        ])->assertOk();

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 8,
        ])->assertOk()
            ->assertJsonPath('data.nota', '11.00')
            ->assertJsonPath('data.nota_recuperacion', '8.00')
            ->assertJsonPath('data.nota_considerada', 8)
            ->assertJsonPath('data.resultado', 'desaprobado');
    }

    public function test_alumno_cannot_request_recovery_for_another_student(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 11);
        $otro = $this->createAlumnoUser('alumno.ex.otro');
        $this->enrollAlumno($otro, $ctx['programacion']);

        Sanctum::actingAs($otro);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion", [
            'matricula_id' => $ctx['matricula']->id,
        ])->assertUnprocessable();
    }

    public function test_interactive_start_answer_and_finish(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, withQuestions: true);

        Sanctum::actingAs($ctx['alumno']);
        $preguntas = $this->getJson("/api/v1/examenes-finales/{$ctx['examen']->id}/preguntas")->assertOk();
        $this->assertArrayNotHasKey('es_correcta', $preguntas->json('data.0.opciones.0'));

        $inicio = $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
        ])->assertSuccessful();
        $intentoId = $inicio->json('data.id');

        $correcta = $ctx['opcion_correcta']->id;
        $this->postJson("/api/v1/intentos-examen/{$intentoId}/enviar", [
            'respuestas' => [[
                'pregunta_examen_id' => $ctx['pregunta']->id,
                'opcion_pregunta_id' => $correcta,
                'es_correcta' => false,
                'puntaje_obtenido' => 0,
                'aprobado' => false,
            ]],
        ])->assertSuccessful()
            ->assertJsonPath('data.estado', 'completado')
            ->assertJsonPath('data.puntaje_obtenido', '20.00')
            ->assertJsonPath('data.respuestas.0.es_correcta', true);

        $this->assertEquals('20.00', (string) NotaExamenFinal::query()->first()->nota);
        $this->assertSame(NotaExamenFinal::ORIGEN_INTERACTIVO, NotaExamenFinal::query()->first()->origen);
    }

    public function test_interactive_duplicate_in_progress_is_blocked(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, withQuestions: true);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
        ])->assertSuccessful();
        $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
        ])->assertUnprocessable();
    }

    public function test_interactive_foreign_access_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, withQuestions: true);
        $ajeno = $this->createAlumnoUser('alumno.ex.int.x');

        $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
            'matricula_id' => $ctx['matricula']->id,
        ])->assertForbidden();
    }

    public function test_interactive_wrong_option_scores_zero_despite_client_flags(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, withQuestions: true);

        Sanctum::actingAs($ctx['alumno']);
        $intentoId = $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
        ])->json('data.id');

        $this->postJson("/api/v1/intentos-examen/{$intentoId}/enviar", [
            'respuestas' => [[
                'pregunta_examen_id' => $ctx['pregunta']->id,
                'opcion_pregunta_id' => $ctx['opcion_incorrecta']->id,
                'es_correcta' => true,
                'puntaje_obtenido' => 99,
                'aprobado' => true,
            ]],
        ])->assertSuccessful()
            ->assertJsonPath('data.puntaje_obtenido', '0.00')
            ->assertJsonPath('data.respuestas.0.es_correcta', false);
    }

    public function test_cannot_replace_question_options_after_answers_exist(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin, withQuestions: true);

        Sanctum::actingAs($ctx['alumno']);
        $intentoId = $this->postJson('/api/v1/intentos-examen/iniciar', [
            'examen_final_id' => $ctx['examen']->id,
        ])->json('data.id');
        $this->postJson("/api/v1/intentos-examen/{$intentoId}/enviar", [
            'respuestas' => [[
                'pregunta_examen_id' => $ctx['pregunta']->id,
                'opcion_pregunta_id' => $ctx['opcion_correcta']->id,
            ]],
        ])->assertSuccessful();

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/preguntas-examen/{$ctx['pregunta']->id}", [
            'opciones' => [
                ['texto' => 'Nueva A', 'es_correcta' => true, 'orden' => 1],
                ['texto' => 'Nueva B', 'es_correcta' => false, 'orden' => 2],
            ],
        ])->assertUnprocessable();
        $this->deleteJson("/api/v1/preguntas-examen/{$ctx['pregunta']->id}")->assertUnprocessable();
    }

    public function test_calificacion_service_uses_considered_exam_note(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $ctx['programacion']->id,
            'codigo' => 'EXAMEN',
            'origen' => 'examen_final',
            'nombre' => 'Examen',
            'porcentaje' => 100,
            'orden' => 1,
        ]);

        $this->postJson("/api/v1/matriculas/{$ctx['matricula']->id}/calificaciones/calcular")
            ->assertCreated()
            ->assertJsonPath('data.nota_examen_final', '16.00')
            ->assertJsonPath('data.nota_final', '16.00');
    }

    public function test_grading_writes_audit_without_secrets(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        $this->assertDatabaseHas('auditorias', [
            'usuario_id' => $admin->id,
            'accion' => 'CREATE',
            'tabla_afectada' => 'notas_examen_final',
        ]);
        $row = DB::table('auditorias')->where('tabla_afectada', 'notas_examen_final')->latest('id')->first();
        $this->assertStringNotContainsString('contrasena', (string) $row->datos_despues);
        $this->assertStringNotContainsString('password', (string) $row->datos_despues);
    }

    public function test_existing_task_module_is_untouched_by_exam_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);
        $this->grade($ctx, 16);

        $this->assertDatabaseMissing('entregas_tarea', ['matricula_id' => $ctx['matricula']->id]);
        $this->assertDatabaseCount('tareas', 0);
    }

    public function test_admin_can_manage_questions_and_alumno_does_not_see_keys(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeContext($admin);

        $created = $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/preguntas", [
            'enunciado' => '¿Cuál es el primer libro?',
            'puntaje' => 5,
            'tipo' => 'seleccion_unica',
            'opciones' => [
                ['texto' => 'Génesis', 'es_correcta' => true, 'orden' => 1],
                ['texto' => 'Éxodo', 'es_correcta' => true, 'orden' => 2],
            ],
        ])->assertUnprocessable();

        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/preguntas", [
            'enunciado' => '¿Cuál es el primer libro?',
            'puntaje' => 5,
            'tipo' => 'seleccion_unica',
            'opciones' => [
                ['texto' => 'Génesis', 'es_correcta' => true, 'orden' => 1],
                ['texto' => 'Éxodo', 'es_correcta' => false, 'orden' => 2],
            ],
        ])->assertSuccessful()->assertJsonPath('data.opciones.0.es_correcta', true);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/preguntas", [
            'enunciado' => 'Hack',
            'puntaje' => 1,
            'opciones' => [['texto' => 'A', 'es_correcta' => true]],
        ])->assertForbidden();
    }

    /**
     * @return array{
     *     programacion: ProgramacionAcademica,
     *     examen: ExamenFinal,
     *     alumno: Usuario,
     *     matricula: Matricula,
     *     docente: Usuario|null,
     *     pregunta: PreguntaExamen|null,
     *     opcion_correcta: OpcionPregunta|null,
     *     opcion_incorrecta: OpcionPregunta|null
     * }
     */
    private function makeContext(
        Usuario $admin,
        bool $assignDocente = false,
        bool $withQuestions = false,
        string $alumnoUsername = 'alumno.ex.ok',
        string $codigo = 'EX-OK',
    ): array {
        $programacion = $this->createProgramacion($codigo);
        $examen = ExamenFinal::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Evaluación 1',
            'descripcion' => 'Examen de la etapa 6-4',
            'puntaje_maximo' => 20,
            'nota_minima_aprobatoria' => 14,
            'activo' => true,
            'creado_por_usuario_id' => $admin->id,
        ]);
        $alumno = $this->createAlumnoUser($alumnoUsername);
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $docente = null;
        if ($assignDocente) {
            $docente = $this->createDocenteUser('docente.ex.ok');
            $this->assignDocente($docente, $programacion);
        }

        $pregunta = null;
        $opcionCorrecta = null;
        $opcionIncorrecta = null;
        if ($withQuestions) {
            $pregunta = PreguntaExamen::query()->create([
                'examen_final_id' => $examen->id,
                'orden' => 1,
                'tipo' => 'seleccion_unica',
                'enunciado' => '¿2 + 2?',
                'puntaje' => 20,
                'activo' => true,
            ]);
            $opcionCorrecta = OpcionPregunta::query()->create([
                'pregunta_examen_id' => $pregunta->id,
                'texto' => '4',
                'es_correcta' => true,
                'orden' => 1,
            ]);
            $opcionIncorrecta = OpcionPregunta::query()->create([
                'pregunta_examen_id' => $pregunta->id,
                'texto' => '5',
                'es_correcta' => false,
                'orden' => 2,
            ]);
        }

        Sanctum::actingAs($admin);

        return [
            'programacion' => $programacion,
            'examen' => $examen,
            'alumno' => $alumno,
            'matricula' => $matricula,
            'docente' => $docente,
            'pregunta' => $pregunta,
            'opcion_correcta' => $opcionCorrecta,
            'opcion_incorrecta' => $opcionIncorrecta,
        ];
    }

    private function grade(array $ctx, int|float $nota): void
    {
        Sanctum::actingAs($this->actingAsAdmin());
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => $nota,
        ])->assertOk();
    }

    private function requestRecovery(array $ctx): SolicitudRecuperacionExamen
    {
        $this->grade($ctx, 11);
        Sanctum::actingAs($ctx['alumno']);
        $id = $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/recuperacion")
            ->assertSuccessful()
            ->json('data.id');

        return SolicitudRecuperacionExamen::query()->findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function examenPayload(int $programacionId, string $titulo, int|float $puntaje = 20, int|float $minima = 14): array
    {
        return [
            'programacion_academica_id' => $programacionId,
            'titulo' => $titulo,
            'descripcion' => 'Descripción',
            'puntaje_maximo' => $puntaje,
            'nota_minima_aprobatoria' => $minima,
            'activo' => true,
        ];
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
