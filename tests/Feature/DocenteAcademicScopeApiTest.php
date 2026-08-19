<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AsistenciaQrException;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\SesionAsistenciaQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class DocenteAcademicScopeApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_docente_lists_only_assigned_programaciones(): void
    {
        $docente = $this->createDocenteUser();
        $ownA = $this->createScopeContext('OWN-A', $docente);
        $ownB = $this->createScopeContext('OWN-B', $docente);
        $other = $this->createScopeContext('OTH-C');

        $ids = collect($this->getJson('/api/v1/programaciones-academicas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($ownA['programacion']->id));
        $this->assertTrue($ids->contains($ownB['programacion']->id));
        $this->assertFalse($ids->contains($other['programacion']->id));
    }

    public function test_docente_can_view_own_programacion(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-V', $docente);

        $this->getJson("/api/v1/programaciones-academicas/{$own['programacion']->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $own['programacion']->id);
    }

    public function test_docente_cannot_view_foreign_programacion(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-V');

        $this->getJson("/api/v1/programaciones-academicas/{$other['programacion']->id}")
            ->assertForbidden();
    }

    public function test_docente_can_view_own_sessions(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-S', $docente);

        $this->getJson("/api/v1/sesiones/{$own['sesion']->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $own['sesion']->id);

        $ids = collect($this->getJson("/api/v1/sesiones?programacion_academica_id={$own['programacion']->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own['sesion']->id));
    }

    public function test_docente_cannot_view_foreign_sessions(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-S');

        $this->getJson("/api/v1/sesiones/{$other['sesion']->id}")->assertForbidden();
        $this->getJson("/api/v1/sesiones?programacion_academica_id={$other['programacion']->id}")
            ->assertForbidden();
    }

    public function test_docente_unfiltered_session_index_excludes_foreign_sessions(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-SI', $docente);
        $other = $this->createScopeContext('OTH-SI');

        $ids = collect($this->getJson('/api/v1/sesiones?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own['sesion']->id));
        $this->assertFalse($ids->contains($other['sesion']->id));
    }

    public function test_docente_can_generate_qr_for_own_session(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-QR', $docente);

        $this->getJson("/api/v1/sesiones/{$own['sesion']->id}/qr")
            ->assertOk()
            ->assertJsonPath('data.sesion.id', $own['sesion']->id)
            ->assertJsonStructure(['data' => ['token', 'payload', 'sesion']]);
    }

    public function test_docente_cannot_generate_qr_for_foreign_session(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-QR');

        $this->getJson("/api/v1/sesiones/{$other['sesion']->id}/qr")->assertForbidden();
    }

    public function test_docente_lists_only_own_enrollments(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-M', $docente);
        $other = $this->createScopeContext('OTH-M');

        $ids = collect($this->getJson('/api/v1/matriculas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own['matricula']->id));
        $this->assertFalse($ids->contains($other['matricula']->id));

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$own['programacion']->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $own['matricula']->id);
    }

    public function test_docente_cannot_access_foreign_enrollments(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-MF');

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$other['programacion']->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/matriculas/{$other['matricula']->id}")
            ->assertForbidden();
    }

    public function test_docente_can_list_attendance_of_own_session(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-AS', $docente);
        $this->actingAsAdmin();
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $own['sesion']->id,
            'matricula_id' => $own['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($docente);

        $ids = collect($this->getJson("/api/v1/asistencias?sesion_id={$own['sesion']->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($asistencia->id));
    }

    public function test_docente_cannot_list_attendance_of_foreign_session(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-AS');

        $this->getJson("/api/v1/asistencias?sesion_id={$other['sesion']->id}")
            ->assertForbidden();
    }

    public function test_docente_can_create_attendance_on_own_session(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-AC', $docente);

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $own['sesion']->id,
            'matricula_id' => $own['matricula']->id,
            'estado' => 'asistio',
        ])->assertCreated()
            ->assertJsonPath('data.estado', 'asistio')
            ->assertJsonPath('data.sesion_id', $own['sesion']->id);
    }

    public function test_docente_cannot_create_attendance_on_foreign_session(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-AC');

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $other['sesion']->id,
            'matricula_id' => $other['matricula']->id,
            'estado' => 'asistio',
        ])->assertForbidden();
    }

    public function test_docente_can_update_own_attendance(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-AU', $docente);
        $this->actingAsAdmin();
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $own['sesion']->id,
            'matricula_id' => $own['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($docente);

        $this->putJson("/api/v1/asistencias/{$asistencia->id}", [
            'estado' => 'falto',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'falto');
    }

    public function test_docente_cannot_update_foreign_attendance(): void
    {
        $docente = $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-AU');
        $this->actingAsAdmin();
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $other['sesion']->id,
            'matricula_id' => $other['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($docente);

        $this->putJson("/api/v1/asistencias/{$asistencia->id}", [
            'estado' => 'falto',
        ])->assertForbidden();
    }

    public function test_docente_can_update_own_session_tema_and_estado(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-SU', $docente);

        $this->putJson("/api/v1/sesiones/{$own['sesion']->id}", [
            'tema' => 'Fe y obras',
            'estado' => 'realizada',
        ])->assertOk()
            ->assertJsonPath('data.tema', 'Fe y obras')
            ->assertJsonPath('data.estado', 'realizada');
    }

    public function test_docente_cannot_change_session_schedule_fields(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-SL', $docente);

        $this->putJson("/api/v1/sesiones/{$own['sesion']->id}", [
            'orden' => 9,
            'inicio_at' => '2026-09-08 10:00:00',
            'fin_at' => '2026-09-08 12:00:00',
        ])->assertUnprocessable();
    }

    public function test_docente_cannot_update_foreign_session(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-SU');

        $this->putJson("/api/v1/sesiones/{$other['sesion']->id}", [
            'tema' => 'No permitido',
        ])->assertForbidden();
    }

    public function test_docente_cannot_create_or_update_programacion(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-P', $docente);

        $this->postJson('/api/v1/programaciones-academicas', [
            'curso_id' => $own['programacion']->curso_id,
            'periodo' => '2026-II',
            'grupo' => 'Z',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 10,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
        ])->assertForbidden();

        $this->putJson("/api/v1/programaciones-academicas/{$own['programacion']->id}", [
            'grupo' => 'ZZ',
        ])->assertForbidden();

        $this->postJson("/api/v1/programaciones-academicas/{$own['programacion']->id}/transiciones", [
            'estado' => 'en_curso',
        ])->assertForbidden();
    }

    public function test_docente_cannot_generate_sessions(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-G', $docente);

        $this->postJson("/api/v1/programaciones-academicas/{$own['programacion']->id}/sesiones/generar")
            ->assertForbidden();
    }

    public function test_docente_cannot_create_or_transition_matricula(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-MT', $docente);

        $this->postJson('/api/v1/matriculas', [
            'programacion_academica_id' => $own['programacion']->id,
            'miembro_id' => $own['matricula']->miembro_id,
        ])->assertForbidden();

        $this->postJson("/api/v1/matriculas/{$own['matricula']->id}/transiciones", [
            'estado' => 'retirada',
        ])->assertForbidden();
    }

    public function test_admin_keeps_global_academic_access(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('ADM-O', $docente);
        $other = $this->createScopeContext('ADM-X');
        $this->actingAsAdmin();

        $ids = collect($this->getJson('/api/v1/programaciones-academicas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($own['programacion']->id));
        $this->assertTrue($ids->contains($other['programacion']->id));

        $this->getJson("/api/v1/sesiones/{$other['sesion']->id}")->assertOk();
        $this->getJson("/api/v1/sesiones/{$other['sesion']->id}/qr")->assertOk();
        $this->getJson("/api/v1/matriculas/{$other['matricula']->id}")->assertOk();
    }

    public function test_unauthenticated_academic_requests_are_unauthorized(): void
    {
        $context = $this->createScopeContext('UNAUTH');

        $this->getJson('/api/v1/programaciones-academicas')->assertUnauthorized();
        $this->getJson("/api/v1/sesiones/{$context['sesion']->id}")->assertUnauthorized();
        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
        ])->assertUnauthorized();
    }

    public function test_student_qr_checkin_still_works(): void
    {
        $context = $this->createScopeContext('QR-ST');
        $alumno = $this->createAlumnoFor($context['matricula']);
        Sanctum::actingAs($alumno);

        $token = app(SesionAsistenciaQrTokenService::class)->issue($context['sesion']);

        $this->postJson('/api/v1/asistencias/qr', ['token' => $token])
            ->assertCreated()
            ->assertJsonPath('code', AsistenciaQrException::ASISTENCIA_REGISTRADA)
            ->assertJsonPath('data.estado', 'asistio');
    }

    public function test_docente_without_miembro_id_is_forbidden(): void
    {
        $this->createDocenteUserWithoutMiembro();

        $this->getJson('/api/v1/programaciones-academicas')->assertForbidden();
        $this->getJson('/api/v1/sesiones')->assertForbidden();
        $this->getJson('/api/v1/asistencias')->assertForbidden();
    }

    public function test_two_assigned_teachers_can_manage_the_same_programacion(): void
    {
        $first = $this->createDocenteUser('docente.a');
        $second = $this->createDocenteUser('docente.b');
        $shared = $this->createScopeContext('SHARE', $first);
        $this->assignDocente($second, $shared['programacion']);

        Sanctum::actingAs($first);
        $this->getJson("/api/v1/programaciones-academicas/{$shared['programacion']->id}")->assertOk();
        $this->getJson("/api/v1/sesiones/{$shared['sesion']->id}/qr")->assertOk();

        Sanctum::actingAs($second);
        $this->getJson("/api/v1/programaciones-academicas/{$shared['programacion']->id}")->assertOk();
        $this->putJson("/api/v1/sesiones/{$shared['sesion']->id}", [
            'tema' => 'Tema compartido',
        ])->assertOk()
            ->assertJsonPath('data.tema', 'Tema compartido');
    }

    public function test_docente_can_update_leccion_ids_on_own_session(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-LID', $docente);
        $leccion = Leccion::query()->create([
            'curso_id' => $own['programacion']->curso_id,
            'orden' => 1,
            'nombre' => 'Salvación',
            'activo' => true,
        ]);

        $this->putJson("/api/v1/sesiones/{$own['sesion']->id}", [
            'leccion_ids' => [$leccion->id],
        ])->assertOk()
            ->assertJsonPath('data.lecciones.0.id', $leccion->id);

        $this->assertDatabaseHas('sesion_lecciones', [
            'sesion_id' => $own['sesion']->id,
            'leccion_id' => $leccion->id,
        ]);
    }

    public function test_docente_cannot_update_leccion_ids_on_foreign_session(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-LID');
        $leccion = Leccion::query()->create([
            'curso_id' => $other['programacion']->curso_id,
            'orden' => 1,
            'nombre' => 'Ajena',
            'activo' => true,
        ]);

        $this->putJson("/api/v1/sesiones/{$other['sesion']->id}", [
            'leccion_ids' => [$leccion->id],
        ])->assertForbidden();
    }

    public function test_docente_cannot_create_or_update_leccion_catalog(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-LC', $docente);
        $leccion = Leccion::query()->create([
            'curso_id' => $own['programacion']->curso_id,
            'orden' => 1,
            'nombre' => 'Fe',
            'activo' => true,
        ]);

        $this->postJson('/api/v1/lecciones', [
            'curso_id' => $own['programacion']->curso_id,
            'orden' => 2,
            'nombre' => 'No permitido',
        ])->assertForbidden();

        $this->putJson("/api/v1/lecciones/{$leccion->id}", [
            'nombre' => 'Tampoco',
        ])->assertForbidden();
    }

    public function test_docente_can_list_lecciones_of_assigned_curso(): void
    {
        $docente = $this->createDocenteUser();
        $own = $this->createScopeContext('OWN-LL', $docente);
        $leccion = Leccion::query()->create([
            'curso_id' => $own['programacion']->curso_id,
            'orden' => 1,
            'nombre' => 'Salvación',
            'activo' => true,
        ]);

        $ids = collect($this->getJson("/api/v1/lecciones?curso_id={$own['programacion']->curso_id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($leccion->id));

        $this->getJson("/api/v1/lecciones/{$leccion->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $leccion->id);
    }

    public function test_docente_cannot_list_lecciones_of_foreign_curso(): void
    {
        $this->createDocenteUser();
        $other = $this->createScopeContext('OTH-LL');
        $leccion = Leccion::query()->create([
            'curso_id' => $other['programacion']->curso_id,
            'orden' => 1,
            'nombre' => 'Ajena',
            'activo' => true,
        ]);

        $this->getJson("/api/v1/lecciones?curso_id={$other['programacion']->curso_id}")
            ->assertForbidden();
        $this->getJson("/api/v1/lecciones/{$leccion->id}")->assertForbidden();
    }

    /**
     * @return array{programacion: ProgramacionAcademica, sesion: Sesion, matricula: Matricula}
     */
    private function createScopeContext(string $codigo, ?Usuario $docente = null): array
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);

        $programacion = ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => substr($codigo, -2),
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);

        if ($docente?->miembro_id !== null) {
            $this->assignDocente($docente, $programacion);
        }

        $sesion = Sesion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'orden' => 1,
            'inicio_at' => '2026-09-07 19:00:00',
            'fin_at' => '2026-09-07 21:00:00',
            'tema' => null,
            'estado' => 'programada',
        ]);

        $miembro = Miembro::query()->create([
            'iglesia_id' => $church,
            'tipo_documento' => 'DNI',
            'numero_documento' => sprintf('%08d', abs(crc32($codigo)) % 90000000 + 10000000),
            'nombres' => 'Alumno',
            'apellidos' => $codigo,
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'correo_electronico' => strtolower($codigo).'@mmm.local',
            'telefono' => '977777777',
            'direccion' => 'Dirección alumno',
        ]);

        $matricula = Matricula::query()->create([
            'programacion_academica_id' => $programacion->id,
            'miembro_id' => $miembro->id,
            'fecha_matricula' => now(),
            'estado' => 'activa',
        ]);

        return [
            'programacion' => $programacion,
            'sesion' => $sesion,
            'matricula' => $matricula,
        ];
    }

    private function createAlumnoFor(Matricula $matricula): Usuario
    {
        $usuario = Usuario::query()->create([
            'miembro_id' => $matricula->miembro_id,
            'nombre_usuario' => 'alumno.'.$matricula->id,
            'contrasena' => 'Admin123*',
            'activo' => true,
        ]);

        return $usuario;
    }
}
