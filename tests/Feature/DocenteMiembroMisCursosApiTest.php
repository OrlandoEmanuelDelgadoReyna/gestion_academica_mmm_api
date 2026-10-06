<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Notificacion;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class DocenteMiembroMisCursosApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_docente_without_miembro_role_does_not_see_own_enrollments_in_default_matriculas_index(): void
    {
        $admin = $this->actingAsAdmin();
        $taught = $this->createProgramacion('DM-DOC-T');
        $enrolled = $this->createProgramacion('DM-DOC-E');
        $docente = $this->createDocenteUser('docente.solo');
        $this->assignDocente($docente, $taught);
        $this->enrollAlumno($docente, $enrolled);
        $peer = $this->createAlumnoUser('peer.taught');
        $this->enrollAlumno($peer, $taught);

        Sanctum::actingAs($docente);
        $ids = collect($this->getJson('/api/v1/matriculas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('miembro_id');

        $this->assertTrue($ids->contains($peer->miembro_id));
        $this->assertFalse($ids->contains($docente->miembro_id));
    }

    public function test_docente_plus_miembro_without_enrollments_gets_empty_propias_list(): void
    {
        $taught = $this->createProgramacion('DM-EMPTY-T');
        $docente = $this->createDocenteUser('docente.empty');
        $this->attachMiembroRole($docente);
        $this->assignDocente($docente, $taught);

        Sanctum::actingAs($docente);
        $this->getJson('/api/v1/matriculas?propias=1&per_page=100')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_docente_plus_miembro_propias_returns_only_own_active_enrollments(): void
    {
        $this->actingAsAdmin();
        $taught = $this->createProgramacion('DM-P1-T', 'B');
        $enrolledA = $this->createProgramacion('DM-P1-E', 'A');
        $enrolledB = $this->createProgramacion('DM-P1-F', 'C');
        $foreign = $this->createProgramacion('DM-P1-X', 'D');

        $docente = $this->createDocenteUser('ruth.dual');
        $this->attachMiembroRole($docente);
        $this->assignDocente($docente, $taught);
        $miaA = $this->enrollAlumno($docente, $enrolledA);
        $miaB = $this->enrollAlumno($docente, $enrolledB);
        $this->enrollAlumno($docente, $foreign, 'retirada');

        $peer = $this->createAlumnoUser('peer.dual');
        $this->enrollAlumno($peer, $taught);
        $this->enrollAlumno($peer, $enrolledA);

        Sanctum::actingAs($docente);
        $payload = $this->getJson('/api/v1/matriculas?propias=1&per_page=100')
            ->assertOk()
            ->json('data');
        $ids = collect($payload)->pluck('id');
        $programacionIds = collect($payload)->pluck('programacion_academica_id');

        $this->assertTrue($ids->contains($miaA->id));
        $this->assertTrue($ids->contains($miaB->id));
        $this->assertCount(2, $ids);
        $this->assertTrue($programacionIds->contains($enrolledA->id));
        $this->assertTrue($programacionIds->contains($enrolledB->id));
        $this->assertFalse($programacionIds->contains($taught->id));
        $this->assertFalse($programacionIds->contains($foreign->id));
        $this->assertFalse(collect($payload)->pluck('miembro_id')->contains($peer->miembro_id));
    }

    public function test_docente_plus_miembro_programaciones_index_still_only_taught_courses(): void
    {
        $taught = $this->createProgramacion('DM-IDX-T');
        $enrolled = $this->createProgramacion('DM-IDX-E');
        $docente = $this->createDocenteUser('docente.idx');
        $this->attachMiembroRole($docente);
        $this->assignDocente($docente, $taught);
        $this->enrollAlumno($docente, $enrolled);

        Sanctum::actingAs($docente);
        $ids = collect($this->getJson('/api/v1/programaciones-academicas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($taught->id));
        $this->assertFalse($ids->contains($enrolled->id));
    }

    public function test_docente_plus_miembro_can_list_tareas_of_enrolled_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $taught = $this->createProgramacion('DM-TAR-T');
        $enrolled = $this->createProgramacion('DM-TAR-E');
        $foreign = $this->createProgramacion('DM-TAR-X');
        $tareaEnrolled = $this->createTarea($enrolled, (int) $admin->id, 'Como alumno');
        $this->createTarea($taught, (int) $admin->id, 'Como docente');
        $tareaForeign = $this->createTarea($foreign, (int) $admin->id, 'Ajena');

        $docente = $this->createDocenteUser('docente.tar.dual');
        $this->attachMiembroRole($docente);
        $this->assignDocente($docente, $taught);
        $this->enrollAlumno($docente, $enrolled);

        Sanctum::actingAs($docente);
        $ids = collect($this->getJson("/api/v1/tareas?programacion_academica_id={$enrolled->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($tareaEnrolled->id));
        $this->assertCount(1, $ids);

        $this->getJson("/api/v1/tareas/{$tareaEnrolled->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tareaEnrolled->id);

        $this->getJson("/api/v1/tareas?programacion_academica_id={$foreign->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/tareas/{$tareaForeign->id}")
            ->assertForbidden();
    }

    public function test_docente_plus_miembro_cannot_create_tarea_in_enrolled_only_programacion(): void
    {
        $this->actingAsAdmin();
        $enrolled = $this->createProgramacion('DM-TAR-NOCREATE');
        $docente = $this->createDocenteUser('docente.tar.alumno');
        $this->attachMiembroRole($docente);
        $this->enrollAlumno($docente, $enrolled);

        Sanctum::actingAs($docente);
        $this->postJson('/api/v1/tareas', [
            'programacion_academica_id' => $enrolled->id,
            'titulo' => 'No debería',
            'publicado_at' => now()->toDateTimeString(),
            'puntaje_maximo' => 20,
        ])->assertForbidden();
    }

    public function test_docente_plus_miembro_receives_tarea_notice_only_for_related_programaciones(): void
    {
        $admin = $this->actingAsAdmin();
        $taught = $this->createProgramacion('DM-NOT-T', 'B');
        $enrolled = $this->createProgramacion('DM-NOT-E', 'A');
        $foreign = $this->createProgramacion('DM-NOT-X', 'C');

        $docente = $this->createDocenteUser('ruth.notices');
        $this->attachMiembroRole($docente);
        $this->assignDocente($docente, $taught);
        $this->enrollAlumno($docente, $enrolled);
        $ajenoAlumno = $this->createAlumnoUser('ajeno.notices');
        $this->enrollAlumno($ajenoAlumno, $foreign);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/tareas', [
            'programacion_academica_id' => $enrolled->id,
            'titulo' => 'Derechos del niño',
            'publicado_at' => now()->toDateTimeString(),
            'fecha_limite_at' => now()->addDays(7)->toDateTimeString(),
            'puntaje_maximo' => 20,
        ])->assertCreated();

        $asAlumno = Notificacion::query()
            ->where('tipo', 'tarea')
            ->where('titulo', 'Nueva tarea publicada')
            ->where('contenido', 'Se publicó la tarea "Derechos del niño".')
            ->first();
        $this->assertNotNull($asAlumno);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $asAlumno->id,
            'usuario_id' => $docente->id,
        ]);

        $this->postJson('/api/v1/tareas', [
            'programacion_academica_id' => $taught->id,
            'titulo' => 'Partitura coral',
            'publicado_at' => now()->toDateTimeString(),
            'fecha_limite_at' => now()->addDays(7)->toDateTimeString(),
            'puntaje_maximo' => 20,
        ])->assertCreated();

        $asDocente = Notificacion::query()
            ->where('tipo', 'tarea')
            ->where('contenido', 'Se publicó la tarea "Partitura coral".')
            ->first();
        $this->assertNotNull($asDocente);
        $this->assertDatabaseHas('notificacion_destinatarios', [
            'notificacion_id' => $asDocente->id,
            'usuario_id' => $docente->id,
        ]);

        $this->postJson('/api/v1/tareas', [
            'programacion_academica_id' => $foreign->id,
            'titulo' => 'Tarea ajena',
            'publicado_at' => now()->toDateTimeString(),
            'fecha_limite_at' => now()->addDays(7)->toDateTimeString(),
            'puntaje_maximo' => 20,
        ])->assertCreated();

        $ajena = Notificacion::query()
            ->where('tipo', 'tarea')
            ->where('contenido', 'Se publicó la tarea "Tarea ajena".')
            ->first();
        $this->assertNotNull($ajena);
        $this->assertDatabaseMissing('notificacion_destinatarios', [
            'notificacion_id' => $ajena->id,
            'usuario_id' => $docente->id,
        ]);
    }

    public function test_docente_plus_miembro_propias_includes_taught_and_enrolled_only_courses(): void
    {
        $this->actingAsAdmin();
        $taller = $this->createProgramacion('DM-RUTH-TALLER', 'B', 'Taller Musical');
        $formacion = $this->createProgramacion('DM-RUTH-FORM', 'A', 'Formación de maestros');
        $taughtOnly = $this->createProgramacion('DM-RUTH-DICTA', 'C', 'Curso que solo dicta');
        $foreign = $this->createProgramacion('DM-RUTH-AJENA', 'D', 'Curso ajeno');

        $ruth = $this->createDocenteUser('ruth.quispe');
        $this->attachMiembroRole($ruth);
        $this->assignDocente($ruth, $taller);
        $this->assignDocente($ruth, $taughtOnly);
        $tallerMatricula = $this->enrollAlumno($ruth, $taller);
        $formacionMatricula = $this->enrollAlumno($ruth, $formacion);
        $this->enrollAlumno($ruth, $foreign, 'retirada');
        $completadaProg = $this->createProgramacion('DM-RUTH-DONE', 'E', 'Curso completado');
        $this->enrollAlumno($ruth, $completadaProg, 'completada');

        $peer = $this->createAlumnoUser('peer.ruth');
        $peerMatricula = $this->enrollAlumno($peer, $formacion);

        Sanctum::actingAs($ruth);
        $payload = $this->getJson('/api/v1/matriculas?propias=1&estado=activa&per_page=100&page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'programacion_academica_id',
                        'miembro_id',
                        'estado',
                        'programacion_academica' => [
                            'id',
                            'grupo',
                            'curso' => ['id', 'nombre'],
                        ],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'total', 'per_page'],
            ])
            ->json();

        $data = collect($payload['data']);
        $ids = $data->pluck('id');
        $programacionIds = $data->pluck('programacion_academica_id');
        $nombres = $data->pluck('programacion_academica.curso.nombre');

        $this->assertTrue($ids->contains($tallerMatricula->id));
        $this->assertTrue($ids->contains($formacionMatricula->id));
        $this->assertCount(2, $ids);
        $this->assertSame(2, (int) $payload['meta']['total']);
        $this->assertTrue($programacionIds->contains($taller->id));
        $this->assertTrue($programacionIds->contains($formacion->id));
        $this->assertFalse($programacionIds->contains($taughtOnly->id));
        $this->assertFalse($programacionIds->contains($foreign->id));
        $this->assertFalse($programacionIds->contains($completadaProg->id));
        $this->assertFalse($ids->contains($peerMatricula->id));
        $this->assertTrue($nombres->contains('Taller Musical'));
        $this->assertTrue($nombres->contains('Formación de maestros'));

        $pageOne = $this->getJson('/api/v1/matriculas?propias=1&estado=activa&per_page=1&page=1')
            ->assertOk()
            ->json();
        $pageTwo = $this->getJson('/api/v1/matriculas?propias=1&estado=activa&per_page=1&page=2')
            ->assertOk()
            ->json();
        $pagedIds = collect($pageOne['data'])->pluck('id')
            ->merge(collect($pageTwo['data'])->pluck('id'));
        $this->assertSame(2, (int) $pageOne['meta']['last_page']);
        $this->assertTrue($pagedIds->contains($tallerMatricula->id));
        $this->assertTrue($pagedIds->contains($formacionMatricula->id));
        $this->assertCount(2, $pagedIds->unique());
    }

    public function test_plain_miembro_enrollment_list_is_unchanged(): void
    {
        $this->actingAsAdmin();
        $propia = $this->createProgramacion('DM-MEM-OK');
        $ajena = $this->createProgramacion('DM-MEM-NO');
        $alumno = $this->createAlumnoUser('miembro.plain');
        $mia = $this->enrollAlumno($alumno, $propia);
        $otro = $this->createAlumnoUser('otro.plain');
        $this->enrollAlumno($otro, $ajena);

        Sanctum::actingAs($alumno);
        $ids = collect($this->getJson('/api/v1/matriculas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mia->id));
        $this->assertCount(1, $ids);

        $propias = collect($this->getJson('/api/v1/matriculas?propias=1&per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');
        $this->assertTrue($propias->contains($mia->id));
        $this->assertCount(1, $propias);
    }

    private function createProgramacion(string $codigo, string $grupo = 'A', string $nombre = ''): ProgramacionAcademica
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => $nombre !== '' ? $nombre : "Curso {$codigo}",
            'activo' => true,
        ]);

        return ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => $grupo,
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }

    private function createTarea(ProgramacionAcademica $programacion, int $actorId, string $titulo): Tarea
    {
        return Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => $titulo,
            'descripcion' => 'Desc',
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 20,
            'creado_por_usuario_id' => $actorId,
        ]);
    }
}
