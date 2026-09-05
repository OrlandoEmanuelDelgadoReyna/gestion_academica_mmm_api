<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\EntregaTarea;
use App\Models\ExamenFinal;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Tarea;
use App\Models\TipoCertificado;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class Etapa2ReglasNegocioApiTest extends TestCase
{
    use AuthenticatesApiUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake('local');
    }

    public function test_creating_examen_notifies_enrolled_alumnos_with_course_theme_date_and_description(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('E2-NTF');
        $alumno = $this->createAlumnoUser('alumno.e2.notify');
        $this->enrollAlumno($alumno, $programacion);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/examenes-finales', [
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Tema del examen',
            'descripcion' => 'Traer Biblia',
            'inicio_at' => '2026-09-15 09:00:00',
            'puntaje_maximo' => 20,
            'nota_minima_aprobatoria' => 14,
            'activo' => true,
        ])->assertSuccessful();

        $notificacion = DB::table('notificaciones')
            ->where('tipo', 'examen')
            ->where('titulo', 'Examen programado')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($notificacion);
        $contenido = (string) $notificacion->contenido;
        $this->assertStringContainsString('Curso E2-NTF', $contenido);
        $this->assertStringContainsString('Tema del examen', $contenido);
        $this->assertStringContainsString('Traer Biblia', $contenido);
        $this->assertStringContainsString('2026-09-15', $contenido);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $alumno->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $admin->id,
        ]);
    }

    public function test_generar_recuperacion_once_notifies_only_failed_candidates(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeExamContext('E2-REC', 'alumno.e2.fail', assignDocente: true);
        $aprobado = $this->createAlumnoUser('alumno.e2.pass');
        $matriculaAprobado = $this->enrollAlumno($aprobado, $ctx['programacion']);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 11,
        ])->assertOk();
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$matriculaAprobado->id}", [
            'nota' => 16,
        ])->assertOk();

        Sanctum::actingAs($ctx['docente']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Recuperación presencial',
            'descripcion' => 'Solo desaprobados',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertSuccessful()
            ->assertJsonPath('data.tiene_recuperacion', true)
            ->assertJsonPath('data.recuperacion_titulo', 'Recuperación presencial');

        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Segunda',
            'fecha' => '2026-09-23 10:00:00',
        ])->assertUnprocessable();

        $this->assertSame(1, ExamenFinal::query()->where('programacion_academica_id', $ctx['programacion']->id)->count());

        $notificacion = DB::table('notificaciones')
            ->where('tipo', 'examen')
            ->where('titulo', 'Examen de recuperación')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($notificacion);
        $this->assertStringContainsString('Recuperación presencial', (string) $notificacion->contenido);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $ctx['alumno']->id,
        ]);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $notificacion->id,
            'usuario_id' => $aprobado->id,
        ]);
    }

    public function test_alumno_cannot_generate_recovery_and_unassigned_docente_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeExamContext('E2-REC-X', 'alumno.e2.recx');
        $this->gradeOrdinary($ctx, 11);

        Sanctum::actingAs($ctx['alumno']);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'No',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertForbidden();

        $this->createDocenteUser('docente.e2.rec.x');
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'No',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Recuperación admin',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertSuccessful();
    }

    public function test_docente_registers_recovery_only_for_candidates_and_keeps_ordinary_note(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeExamContext('E2-NOTA', 'alumno.e2.cand', assignDocente: true);
        $aprobado = $this->createAlumnoUser('alumno.e2.nocand');
        $matriculaAprobado = $this->enrollAlumno($aprobado, $ctx['programacion']);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", ['nota' => 11])->assertOk();
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$matriculaAprobado->id}", ['nota' => 16])->assertOk();
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Recuperación',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertSuccessful();

        Sanctum::actingAs($ctx['docente']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", ['nota' => 15])
            ->assertOk()
            ->assertJsonPath('data.nota', '11.00')
            ->assertJsonPath('data.nota_recuperacion', '15.00')
            ->assertJsonPath('data.nota_considerada', 15);

        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$matriculaAprobado->id}", ['nota' => 18])
            ->assertUnprocessable();

        $aprobadoNota = NotaExamenFinal::query()->where('matricula_id', $matriculaAprobado->id)->first();
        $this->assertEquals('16.00', (string) $aprobadoNota->nota);
        $this->assertNull($aprobadoNota->nota_recuperacion);
        $this->assertSame(16.0, $aprobadoNota->notaConsiderada());
    }

    public function test_alumno_cannot_register_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeExamContext('E2-ALU-N', 'alumno.e2.grade');

        Sanctum::actingAs($ctx['alumno']);
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 20,
        ])->assertForbidden();
    }

    public function test_final_grade_uses_40_60_and_pass_mark_14(): void
    {
        $admin = $this->actingAsAdmin();
        $pass = $this->makeGradedProgram('E2-FIN-OK', 'alumno.e2.fin.ok', notaTarea: 8, notaExamen: 8);
        $fail = $this->makeGradedProgram('E2-FIN-NO', 'alumno.e2.fin.no', notaTarea: 8, notaExamen: 5);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/matriculas/{$pass['matricula']->id}/calificaciones/calcular")
            ->assertSuccessful()
            ->assertJsonPath('data.promedio_tareas', '16.00')
            ->assertJsonPath('data.nota_examen_final', '16.00')
            ->assertJsonPath('data.nota_final', '16.00')
            ->assertJsonPath('data.estado', 'aprobada');

        $this->postJson("/api/v1/matriculas/{$fail['matricula']->id}/calificaciones/calcular")
            ->assertSuccessful()
            ->assertJsonPath('data.promedio_tareas', '16.00')
            ->assertJsonPath('data.nota_examen_final', '10.00')
            ->assertJsonPath('data.nota_final', '12.40')
            ->assertJsonPath('data.estado', 'desaprobada');
    }

    public function test_recovery_changes_considered_exam_and_final_grade(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeGradedProgram('E2-FIN-R', 'alumno.e2.fin.r', notaTarea: 8, notaExamen: 5);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Recuperación',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertSuccessful();
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 9,
        ])->assertOk()
            ->assertJsonPath('data.nota', '5.00')
            ->assertJsonPath('data.nota_recuperacion', '9.00')
            ->assertJsonPath('data.nota_considerada', 9);

        $this->postJson("/api/v1/matriculas/{$ctx['matricula']->id}/calificaciones/calcular")
            ->assertSuccessful()
            ->assertJsonPath('data.nota_examen_final', '18.00')
            ->assertJsonPath('data.nota_final', '17.20')
            ->assertJsonPath('data.estado', 'aprobada');
    }

    public function test_certificate_eligibility_requires_final_14_and_attendance_80(): void
    {
        $admin = $this->actingAsAdmin();
        $ok = $this->makeGradedProgram('E2-CE-OK', 'alumno.e2.ce.ok', presentes: 14, totalSesiones: 20, justificados: 2);
        $lowAttendance = $this->makeGradedProgram('E2-CE-AS', 'alumno.e2.ce.as', presentes: 15, totalSesiones: 20);
        $lowGrade = $this->makeGradedProgram('E2-CE-NO', 'alumno.e2.ce.no', notaExamen: 5, presentes: 16, totalSesiones: 20);
        $seventyNine = $this->makeGradedProgram('E2-CE-79', 'alumno.e2.ce.79', presentes: 79, totalSesiones: 100);

        Sanctum::actingAs($admin);
        $this->getJson($this->eligibilityUrl($ok))->assertOk()->assertJsonPath('data.elegible', true);
        $this->getJson($this->eligibilityUrl($lowAttendance))->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.asistencia_cumple', false);
        $this->getJson($this->eligibilityUrl($lowGrade))->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.curso_aprobado', false);
        $this->getJson($this->eligibilityUrl($seventyNine))->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.asistencia_porcentaje', 79);
    }

    public function test_recovery_recalculates_certificate_eligibility(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeGradedProgram('E2-CE-R', 'alumno.e2.ce.r', notaExamen: 5, presentes: 16, totalSesiones: 20);

        Sanctum::actingAs($admin);
        $this->getJson($this->eligibilityUrl($ctx))->assertOk()->assertJsonPath('data.elegible', false);

        $this->postJson("/api/v1/examenes-finales/{$ctx['examen']->id}/generar-recuperacion", [
            'titulo' => 'Recuperación',
            'fecha' => '2026-09-22 10:00:00',
        ])->assertSuccessful();
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => 9,
        ])->assertOk();

        $this->getJson($this->eligibilityUrl($ctx))
            ->assertOk()
            ->assertJsonPath('data.elegible', true)
            ->assertJsonPath('data.curso_aprobado', true);
    }

    public function test_manual_completada_transition_is_rejected(): void
    {
        $admin = $this->actingAsAdmin();
        $ctx = $this->makeGradedProgram('E2-MT-M', 'alumno.e2.mt.m');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/matriculas/{$ctx['matricula']->id}/transiciones", [
            'estado' => 'completada',
        ])->assertUnprocessable();

        $this->assertSame('activa', $ctx['matricula']->fresh()->estado);
    }

    public function test_matricula_completes_automatically_when_program_closes_and_rules_are_met(): void
    {
        $admin = $this->actingAsAdmin();
        $ok = $this->makeGradedProgram('E2-MT-OK', 'alumno.e2.mt.ok', presentes: 14, totalSesiones: 20, justificados: 2);
        $fail = $this->makeGradedProgram('E2-MT-NO', 'alumno.e2.mt.no', notaExamen: 5, presentes: 16, totalSesiones: 20);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/programaciones-academicas/{$ok['programacion']->id}/transiciones", [
            'estado' => 'cerrada',
        ])->assertSuccessful();
        $this->postJson("/api/v1/programaciones-academicas/{$fail['programacion']->id}/transiciones", [
            'estado' => 'cerrada',
        ])->assertSuccessful();

        $this->assertSame('completada', $ok['matricula']->fresh()->estado);
        $this->assertSame('activa', $fail['matricula']->fresh()->estado);
    }

    public function test_completada_does_not_depend_on_certificate_issuance(): void
    {
        $admin = $this->actingAsAdmin();
        $withoutClose = $this->makeGradedProgram('E2-MT-EM', 'alumno.e2.mt.em', presentes: 16, totalSesiones: 20);
        $withClose = $this->makeGradedProgram('E2-MT-CL', 'alumno.e2.mt.cl', presentes: 16, totalSesiones: 20);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/certificados/emitir', [
            'miembro_id' => $withoutClose['alumno']->miembro_id,
            'tipo_certificado_id' => $this->tipoAcademicoId(),
            'programacion_academica_id' => $withoutClose['programacion']->id,
            'destinatario' => $withoutClose['alumno']->miembro?->nombre_completo,
        ])->assertCreated();
        $this->assertSame('activa', $withoutClose['matricula']->fresh()->estado);
        $this->assertTrue(Certificado::query()->where('miembro_id', $withoutClose['alumno']->miembro_id)->exists());

        $this->postJson("/api/v1/programaciones-academicas/{$withClose['programacion']->id}/transiciones", [
            'estado' => 'cerrada',
        ])->assertSuccessful();
        $this->assertSame('completada', $withClose['matricula']->fresh()->estado);
        $this->assertFalse(Certificado::query()->where('miembro_id', $withClose['alumno']->miembro_id)->exists());
    }

    /**
     * @return array{programacion: ProgramacionAcademica, examen: ExamenFinal, alumno: Usuario, matricula: Matricula, docente: ?Usuario}
     */
    private function makeExamContext(string $codigo, string $alumnoUsername, bool $assignDocente = false): array
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion($codigo);
        $examen = ExamenFinal::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Evaluación ordinaria',
            'descripcion' => 'Presencial',
            'puntaje_maximo' => 20,
            'nota_minima_aprobatoria' => 14,
            'activo' => true,
            'creado_por_usuario_id' => $admin->id,
        ]);
        $alumno = $this->createAlumnoUser($alumnoUsername);
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $docente = null;
        if ($assignDocente) {
            $docente = $this->createDocenteUser('docente.'.$codigo);
            $this->assignDocente($docente, $programacion);
        }

        Sanctum::actingAs($admin);

        return compact('programacion', 'examen', 'alumno', 'matricula', 'docente');
    }

    /** @param array{examen: ExamenFinal, matricula: Matricula} $ctx */
    private function gradeOrdinary(array $ctx, float $nota): void
    {
        Sanctum::actingAs($this->actingAsAdmin());
        $this->putJson("/api/v1/examenes-finales/{$ctx['examen']->id}/notas/{$ctx['matricula']->id}", [
            'nota' => $nota,
        ])->assertOk();
    }

    /**
     * @return array{
     *     programacion: ProgramacionAcademica,
     *     examen: ExamenFinal,
     *     alumno: Usuario,
     *     matricula: Matricula
     * }
     */
    private function makeGradedProgram(
        string $codigo,
        string $alumnoUsername,
        float $notaTarea = 8,
        float $notaExamen = 8,
        int $presentes = 0,
        int $totalSesiones = 0,
        int $justificados = 0,
    ): array {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion($codigo);
        $alumno = $this->createAlumnoUser($alumnoUsername);
        $matricula = $this->enrollAlumno($alumno, $programacion);

        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'codigo' => 'TAREAS',
            'origen' => 'tareas',
            'nombre' => 'Tareas',
            'porcentaje' => 40,
            'orden' => 1,
        ]);
        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'codigo' => 'EXAMEN',
            'origen' => 'examen_final',
            'nombre' => 'Examen final',
            'porcentaje' => 60,
            'orden' => 2,
        ]);

        $tarea = Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Tarea 1',
            'descripcion' => null,
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 10,
            'creado_por_usuario_id' => $admin->id,
        ]);
        EntregaTarea::query()->create([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matricula->id,
            'contenido' => 'Entrega',
            'entregado_at' => now(),
            'nota' => $notaTarea,
            'calificado_at' => now(),
            'calificado_por_usuario_id' => $admin->id,
        ]);

        $examen = ExamenFinal::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Examen final',
            'descripcion' => null,
            'puntaje_maximo' => 10,
            'nota_minima_aprobatoria' => 7,
            'activo' => true,
            'creado_por_usuario_id' => $admin->id,
        ]);
        NotaExamenFinal::query()->create([
            'examen_final_id' => $examen->id,
            'matricula_id' => $matricula->id,
            'nota' => $notaExamen,
            'origen' => NotaExamenFinal::ORIGEN_MANUAL,
            'calificado_por_usuario_id' => $admin->id,
            'calificado_at' => now(),
        ]);

        for ($i = 1; $i <= $totalSesiones; $i++) {
            $sesion = Sesion::query()->create([
                'programacion_academica_id' => $programacion->id,
                'orden' => $i,
                'inicio_at' => now()->subDays($totalSesiones - $i + 1),
                'fin_at' => now()->subDays($totalSesiones - $i + 1)->addHours(2),
                'tema' => "Sesión {$i}",
                'estado' => 'realizada',
            ]);
            $estado = null;
            if ($i <= $presentes) {
                $estado = 'asistio';
            } elseif ($i <= $presentes + $justificados) {
                $estado = 'justificado';
            }
            if ($estado !== null) {
                Asistencia::query()->create([
                    'sesion_id' => $sesion->id,
                    'matricula_id' => $matricula->id,
                    'estado' => $estado,
                    'registrado_por_usuario_id' => $admin->id,
                ]);
            }
        }

        Sanctum::actingAs($admin);

        return compact('programacion', 'examen', 'alumno', 'matricula');
    }

    /** @param array{alumno: Usuario, programacion: ProgramacionAcademica} $context */
    private function eligibilityUrl(array $context): string
    {
        return '/api/v1/certificados/elegibilidad?'.http_build_query([
            'miembro_id' => $context['alumno']->miembro_id,
            'programacion_academica_id' => $context['programacion']->id,
            'tipo_certificado_id' => $this->tipoAcademicoId(),
        ]);
    }

    private function tipoAcademicoId(): int
    {
        return (int) TipoCertificado::query()->where('codigo', TipoCertificado::CODIGO_ACADEMICO)->value('id');
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
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }
}
