<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class SesionApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_show_existing_session(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-1');

        $this->getJson("/api/v1/sesiones/{$sesion->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $sesion->id)
            ->assertJsonPath('data.programacion_academica_id', $programacion->id)
            ->assertJsonPath('data.estado', 'programada')
            ->assertJsonPath('data.programacion_academica.curso.nombre', 'Curso SES-API-1');
    }

    public function test_show_missing_session_returns_not_found(): void
    {
        $this->getJson('/api/v1/sesiones/999999')->assertNotFound();
    }

    public function test_update_tema_persists(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-2');

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'tema' => 'La fe y las obras',
        ])->assertOk()
            ->assertJsonPath('data.tema', 'La fe y las obras')
            ->assertJsonPath('data.programacion_academica_id', $programacion->id);

        $this->assertDatabaseHas('sesiones', [
            'id' => $sesion->id,
            'programacion_academica_id' => $programacion->id,
            'tema' => 'La fe y las obras',
            'estado' => 'programada',
        ]);
    }

    public function test_update_estado_from_programada_to_realizada(): void
    {
        [, $sesion] = $this->createProgramacionWithSesion('SES-API-3');

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'estado' => 'realizada',
        ])->assertOk()
            ->assertJsonPath('data.estado', 'realizada');

        $this->assertDatabaseHas('sesiones', [
            'id' => $sesion->id,
            'estado' => 'realizada',
        ]);
    }

    public function test_update_rejects_invalid_estado(): void
    {
        [, $sesion] = $this->createProgramacionWithSesion('SES-API-4');

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'estado' => 'abierta',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['estado']);

        $this->assertDatabaseHas('sesiones', [
            'id' => $sesion->id,
            'estado' => 'programada',
        ]);
    }

    public function test_update_rejects_programacion_academica_id_change(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-5', 'G5');
        [$otra] = $this->createProgramacionWithSesion('SES-API-6', 'G6');

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'programacion_academica_id' => $otra->id,
            'tema' => 'No debe moverse',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['programacion_academica_id']);

        $this->assertDatabaseHas('sesiones', [
            'id' => $sesion->id,
            'programacion_academica_id' => $programacion->id,
            'tema' => null,
        ]);
    }

    public function test_update_without_leccion_ids_preserves_existing_lessons(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-7', 'G7');

        $leccion = Leccion::query()->create([
            'curso_id' => $programacion->curso_id,
            'orden' => 1,
            'nombre' => 'Lección 1',
            'activo' => true,
        ]);
        $sesion->lecciones()->sync([$leccion->id]);

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'tema' => 'Tema con lección',
            'estado' => 'programada',
        ])->assertOk();

        $this->assertDatabaseHas('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $leccion->id,
        ]);
    }

    /** @return array{0: ProgramacionAcademica, 1: Sesion} */
    private function createProgramacionWithSesion(string $cursoCodigo, string $grupo = 'A'): array
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $cursoCodigo,
            'nombre' => "Curso {$cursoCodigo}",
            'activo' => true,
        ]);

        $programacion = ProgramacionAcademica::query()->create([
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

        $sesion = Sesion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'orden' => 1,
            'inicio_at' => '2026-09-07 19:00:00',
            'fin_at' => '2026-09-07 21:00:00',
            'tema' => null,
            'estado' => 'programada',
        ]);

        return [$programacion, $sesion];
    }
}
