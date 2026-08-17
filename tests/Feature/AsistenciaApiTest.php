<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class AsistenciaApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_index_filters_by_sesion(): void
    {
        $first = $this->createAttendanceContext('ASI-1');
        $second = $this->createAttendanceContext('ASI-2');

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $first['sesion']->id,
            'matricula_id' => $first['matricula']->id,
            'estado' => 'asistio',
        ])->assertCreated();

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $second['sesion']->id,
            'matricula_id' => $second['matricula']->id,
            'estado' => 'falto',
        ])->assertCreated();

        $this->getJson("/api/v1/asistencias?sesion_id={$first['sesion']->id}&per_page=100")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sesion_id', $first['sesion']->id)
            ->assertJsonPath('data.0.matricula_id', $first['matricula']->id);
    }

    public function test_index_does_not_return_other_session_records(): void
    {
        $first = $this->createAttendanceContext('ASI-3');
        $second = $this->createAttendanceContext('ASI-4');

        Asistencia::query()->create([
            'sesion_id' => $first['sesion']->id,
            'matricula_id' => $first['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);
        Asistencia::query()->create([
            'sesion_id' => $second['sesion']->id,
            'matricula_id' => $second['matricula']->id,
            'estado' => 'falto',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);

        $ids = collect($this->getJson("/api/v1/asistencias?sesion_id={$first['sesion']->id}&per_page=100")
            ->assertOk()
            ->json('data'))->pluck('sesion_id');

        $this->assertTrue($ids->every(fn ($id) => (int) $id === $first['sesion']->id));
        $this->assertFalse($ids->contains($second['sesion']->id));
    }

    public function test_store_valid_attendance(): void
    {
        $context = $this->createAttendanceContext('ASI-5');

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
        ])->assertCreated()
            ->assertJsonPath('data.sesion_id', $context['sesion']->id)
            ->assertJsonPath('data.matricula_id', $context['matricula']->id)
            ->assertJsonPath('data.estado', 'asistio');

        $this->assertDatabaseHas('asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
        ]);
    }

    public function test_store_justificado_without_observacion_is_unprocessable(): void
    {
        $context = $this->createAttendanceContext('ASI-6');

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'justificado',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['observacion']);
    }

    public function test_store_rejects_matricula_from_other_programacion(): void
    {
        $context = $this->createAttendanceContext('ASI-7');
        $otra = $this->createAttendanceContext('ASI-8');

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $otra['matricula']->id,
            'estado' => 'asistio',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['matricula_id']);
    }

    public function test_store_duplicate_returns_unprocessable_not_server_error(): void
    {
        $context = $this->createAttendanceContext('ASI-9');

        $payload = [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
        ];

        $this->postJson('/api/v1/asistencias', $payload)->assertCreated();

        $this->postJson('/api/v1/asistencias', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['matricula_id']);
    }

    public function test_update_estado_persists(): void
    {
        $context = $this->createAttendanceContext('ASI-10');
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);

        $this->putJson("/api/v1/asistencias/{$asistencia->id}", [
            'estado' => 'falto',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'falto');

        $this->assertDatabaseHas('asistencias', [
            'id' => $asistencia->id,
            'estado' => 'falto',
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
        ]);
    }

    public function test_update_rejects_sesion_id_change(): void
    {
        $context = $this->createAttendanceContext('ASI-11');
        $otra = $this->createAttendanceContext('ASI-12');
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);

        $this->putJson("/api/v1/asistencias/{$asistencia->id}", [
            'sesion_id' => $otra['sesion']->id,
            'estado' => 'falto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['sesion_id']);

        $this->assertDatabaseHas('asistencias', [
            'id' => $asistencia->id,
            'sesion_id' => $context['sesion']->id,
        ]);
    }

    public function test_update_rejects_matricula_id_change(): void
    {
        $context = $this->createAttendanceContext('ASI-13');
        $otra = $this->createAttendanceContext('ASI-14');
        $asistencia = Asistencia::query()->create([
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
        ]);

        $this->putJson("/api/v1/asistencias/{$asistencia->id}", [
            'matricula_id' => $otra['matricula']->id,
            'estado' => 'falto',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['matricula_id']);

        $this->assertDatabaseHas('asistencias', [
            'id' => $asistencia->id,
            'matricula_id' => $context['matricula']->id,
        ]);
    }

    public function test_store_forbidden_without_academico_gestionar(): void
    {
        $context = $this->createAttendanceContext('ASI-15');
        $this->actingAsUserWithoutAcademicPermission();

        $this->postJson('/api/v1/asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
        ])->assertForbidden();
    }

    /**
     * @return array{programacion: ProgramacionAcademica, sesion: Sesion, matricula: Matricula}
     */
    private function createAttendanceContext(string $codigo): array
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

    private function actingAsUserWithoutAcademicPermission(): void
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $secretariaRole = (int) DB::table('roles')->where('codigo', 'SECRETARIA')->value('id');
        $now = now();

        $miembro = Miembro::query()->create([
            'iglesia_id' => $church,
            'tipo_documento' => 'DNI',
            'numero_documento' => '11112222',
            'nombres' => 'Secretaria',
            'apellidos' => 'SinPermiso',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'F',
            'correo_electronico' => 'secretaria.noperm@mmm.local',
            'telefono' => '966666666',
            'direccion' => 'Dirección',
        ]);

        $usuario = Usuario::query()->create([
            'miembro_id' => $miembro->id,
            'nombre_usuario' => 'secretaria.noperm',
            'contrasena' => 'Admin123*',
            'activo' => true,
        ]);

        DB::table('usuario_roles')->insert([
            'usuario_id' => $usuario->id,
            'rol_id' => $secretariaRole,
            'asignado_por_usuario_id' => Usuario::query()->where('nombre_usuario', 'admin')->value('id'),
            'asignado_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Sanctum::actingAs($usuario);
    }
}
