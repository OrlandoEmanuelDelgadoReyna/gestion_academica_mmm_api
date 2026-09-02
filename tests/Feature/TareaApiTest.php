<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\EntregaTarea;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class TareaApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_admin_can_list_all_tareas(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-A');
        $ajena = $this->createProgramacion('TAR-B');
        $tareaA = $this->createTarea($propia, (int) $admin->id, 'Tarea A');
        $tareaB = $this->createTarea($ajena, (int) $admin->id, 'Tarea B');

        $ids = collect($this->getJson('/api/v1/tareas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($tareaA->id));
        $this->assertTrue($ids->contains($tareaB->id));
    }

    public function test_admin_can_filter_by_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-FA');
        $ajena = $this->createProgramacion('TAR-FB');
        $tareaA = $this->createTarea($propia, (int) $admin->id, 'Tarea filtro A');
        $tareaB = $this->createTarea($ajena, (int) $admin->id, 'Tarea filtro B');

        $ids = collect($this->getJson("/api/v1/tareas?programacion_academica_id={$propia->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($tareaA->id));
        $this->assertFalse($ids->contains($tareaB->id));
        $this->assertCount(1, $ids);
    }

    public function test_unfiltered_list_still_works_for_admin(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-UNF');
        $tarea = $this->createTarea($programacion, (int) $admin->id);

        $this->getJson('/api/v1/tareas')
            ->assertOk()
            ->assertJsonPath('data.0.id', $tarea->id);
    }

    public function test_assigned_docente_can_list_tareas_of_own_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-DOC-OK');
        $ajena = $this->createProgramacion('TAR-DOC-OTH');
        $mia = $this->createTarea($propia, (int) $admin->id, 'Tarea docente');
        $otra = $this->createTarea($ajena, (int) $admin->id, 'Tarea ajena');

        $docente = $this->createDocenteUser('docente.tar');
        $this->assignDocente($docente, $propia);

        $filtered = collect($this->getJson("/api/v1/tareas?programacion_academica_id={$propia->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($filtered->contains($mia->id));
        $this->assertFalse($filtered->contains($otra->id));

        $unfiltered = collect($this->getJson('/api/v1/tareas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($unfiltered->contains($mia->id));
        $this->assertFalse($unfiltered->contains($otra->id));
    }

    public function test_assigned_docente_can_view_own_tarea(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-DOC-V');
        $tarea = $this->createTarea($programacion, (int) $admin->id, 'Visible docente');
        $docente = $this->createDocenteUser('docente.tar.v');
        $this->assignDocente($docente, $programacion);

        $this->getJson("/api/v1/tareas/{$tarea->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $tarea->id)
            ->assertJsonPath('data.titulo', 'Visible docente');
    }

    public function test_unassigned_docente_cannot_view_foreign_tarea(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('TAR-DOC-X');
        $tarea = $this->createTarea($ajena, (int) $admin->id, 'Secreta');
        $this->createDocenteUser('docente.tar.x');

        $this->getJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_docente_cannot_list_foreign_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-DOC-OWN');
        $ajena = $this->createProgramacion('TAR-DOC-FOR');
        $this->createTarea($ajena, (int) $admin->id);
        $docente = $this->createDocenteUser('docente.tar.f');
        $this->assignDocente($docente, $propia);

        $this->getJson("/api/v1/tareas?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
    }

    public function test_docente_cannot_create_or_update_tarea(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-DOC-MUT');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $docente = $this->createDocenteUser('docente.tar.m');
        $this->assignDocente($docente, $programacion);

        $this->postJson('/api/v1/tareas', $this->tareaPayload($programacion))
            ->assertForbidden();
        $this->putJson("/api/v1/tareas/{$tarea->id}", ['titulo' => 'Hack'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_enrolled_alumno_can_list_and_view_own_programacion_tareas(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-ALU-OK');
        $ajena = $this->createProgramacion('TAR-ALU-OTH');
        $mia = $this->createTarea($propia, (int) $admin->id, 'Tarea alumno');
        $otra = $this->createTarea($ajena, (int) $admin->id, 'Tarea de otro curso');

        $alumno = $this->createAlumnoUser('alumno.tar.ok');
        $this->enrollAlumno($alumno, $propia);

        $ids = collect($this->getJson("/api/v1/tareas?programacion_academica_id={$propia->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mia->id));
        $this->assertFalse($ids->contains($otra->id));

        $this->getJson("/api/v1/tareas/{$mia->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $mia->id);
    }

    public function test_alumno_without_enrollment_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ALU-NO');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $this->createAlumnoUser('alumno.tar.no');

        $this->getJson('/api/v1/tareas')->assertForbidden();
        $this->getJson("/api/v1/tareas?programacion_academica_id={$programacion->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_retirada_enrollment_cannot_access_tareas(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ALU-RET');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.tar.ret');
        $this->enrollAlumno($alumno, $programacion, 'retirada');

        $this->getJson("/api/v1/tareas?programacion_academica_id={$programacion->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_completada_enrollment_cannot_access_tareas(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ALU-COM');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.tar.com');
        $this->enrollAlumno($alumno, $programacion, 'completada');

        $this->getJson("/api/v1/tareas?programacion_academica_id={$programacion->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_alumno_cannot_view_tareas_of_other_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-ALU-OWN');
        $ajena = $this->createProgramacion('TAR-ALU-FOR');
        $secreta = $this->createTarea($ajena, (int) $admin->id, 'No ver');
        $alumno = $this->createAlumnoUser('alumno.tar.for');
        $this->enrollAlumno($alumno, $propia);

        $this->getJson("/api/v1/tareas?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/tareas/{$secreta->id}")
            ->assertForbidden();
    }

    public function test_changing_tarea_ids_does_not_leak_foreign_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('TAR-IDOR-A');
        $ajena = $this->createProgramacion('TAR-IDOR-B');
        $visible = $this->createTarea($propia, (int) $admin->id, 'Propia');
        $secreta = $this->createTarea($ajena, (int) $admin->id, 'Ajena');

        $alumno = $this->createAlumnoUser('alumno.tar.idor');
        $this->enrollAlumno($alumno, $propia);

        $this->getJson("/api/v1/tareas/{$visible->id}")->assertOk();
        $this->getJson("/api/v1/tareas/{$secreta->id}")->assertForbidden();

        $docente = $this->createDocenteUser('docente.tar.idor');
        $this->assignDocente($docente, $propia);

        $this->getJson("/api/v1/tareas/{$visible->id}")->assertOk();
        $this->getJson("/api/v1/tareas/{$secreta->id}")->assertForbidden();
    }

    public function test_alumno_cannot_create_or_update_tarea(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ALU-MUT');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.tar.mut');
        $this->enrollAlumno($alumno, $programacion);

        $this->postJson('/api/v1/tareas', $this->tareaPayload($programacion))
            ->assertForbidden();
        $this->putJson("/api/v1/tareas/{$tarea->id}", ['titulo' => 'Hack'])
            ->assertForbidden();
        $this->deleteJson("/api/v1/tareas/{$tarea->id}")
            ->assertForbidden();
    }

    public function test_admin_still_creates_and_updates_tareas(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ADM-C');

        $id = (int) $this->postJson('/api/v1/tareas', $this->tareaPayload($programacion))
            ->assertCreated()
            ->json('data.id');

        $this->putJson("/api/v1/tareas/{$id}", ['titulo' => 'Tarea actualizada'])
            ->assertOk()
            ->assertJsonPath('data.titulo', 'Tarea actualizada');
    }

    public function test_admin_cannot_store_entrega_for_foreign_matricula(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ENT-IDOR');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.tar.ent');
        $matricula = $this->enrollAlumno($alumno, $programacion);

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'matricula_id' => $matricula->id,
            'contenido' => 'Entrega ajena',
        ])->assertForbidden();

        $this->assertDatabaseCount('entregas_tarea', 0);
    }

    public function test_alumno_cannot_view_another_students_entrega_by_id(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('TAR-ENT-VIEW');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $dueno = $this->createAlumnoUser('alumno.tar.own');
        $ajeno = $this->createAlumnoUser('alumno.tar.spy');
        $matricula = $this->enrollAlumno($dueno, $programacion);
        $this->enrollAlumno($ajeno, $programacion);

        $entrega = EntregaTarea::query()->create([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matricula->id,
            'contenido' => 'Solo el dueño',
            'entregado_at' => now(),
        ]);

        Sanctum::actingAs($ajeno);
        $this->getJson("/api/v1/entregas-tarea/{$entrega->id}")
            ->assertForbidden();
    }

    private function createTarea(
        ProgramacionAcademica $programacion,
        int $actorId,
        string $titulo = 'Tarea 1',
    ): Tarea {
        return Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => $titulo,
            'descripcion' => 'Descripción',
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 20,
            'creado_por_usuario_id' => $actorId,
        ]);
    }

    /** @return array<string, mixed> */
    private function tareaPayload(ProgramacionAcademica $programacion): array
    {
        return [
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Nueva tarea',
            'descripcion' => 'Enunciado',
            'publicado_at' => now()->toDateTimeString(),
            'fecha_limite_at' => now()->addDays(7)->toDateTimeString(),
            'puntaje_maximo' => 20,
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
