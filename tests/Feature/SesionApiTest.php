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

    public function test_assign_one_leccion_to_sesion(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-8', 'G8');
        $leccion = $this->createLeccion($programacion->curso_id, 1, 'Salvación');

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'leccion_ids' => [$leccion->id],
        ])->assertOk()
            ->assertJsonPath('data.lecciones.0.id', $leccion->id)
            ->assertJsonPath('data.lecciones.0.nombre', 'Salvación')
            ->assertJsonMissingPath('data.lecciones.0.pivot');

        $this->assertDatabaseHas('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $leccion->id,
        ]);
    }

    public function test_assign_multiple_lecciones_to_sesion_ordered_by_curso_orden(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-9', 'G9');
        $segunda = $this->createLeccion($programacion->curso_id, 2, 'Arrepentimiento');
        $primera = $this->createLeccion($programacion->curso_id, 1, 'Salvación');

        $nombres = $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'leccion_ids' => [$segunda->id, $primera->id],
        ])->assertOk()
            ->json('data.lecciones');

        $this->assertSame(
            ['Salvación', 'Arrepentimiento'],
            collect($nombres)->pluck('nombre')->all(),
        );
        $this->assertDatabaseCount('sesion_lecciones', 2);
    }

    public function test_empty_leccion_ids_clears_assignments_without_deleting_lessons(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-10', 'G10');
        $leccion = $this->createLeccion($programacion->curso_id, 1, 'Fe');
        $sesion->lecciones()->sync([$leccion->id]);

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'leccion_ids' => [],
        ])->assertOk()
            ->assertJsonPath('data.lecciones', []);

        $this->assertDatabaseMissing('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $leccion->id,
        ]);
        $this->assertDatabaseHas('lecciones', ['id' => $leccion->id, 'nombre' => 'Fe']);
    }

    public function test_leccion_from_another_curso_is_rejected(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-11', 'G11');
        [$otra] = $this->createProgramacionWithSesion('SES-API-12', 'G12');
        $propia = $this->createLeccion($programacion->curso_id, 1, 'Propia');
        $ajena = $this->createLeccion($otra->curso_id, 1, 'Ajena');
        $sesion->lecciones()->sync([$propia->id]);

        $this->putJson("/api/v1/sesiones/{$sesion->id}", [
            'leccion_ids' => [$ajena->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['leccion_ids']);

        $this->assertDatabaseHas('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $propia->id,
        ]);
        $this->assertDatabaseMissing('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $ajena->id,
        ]);
    }

    public function test_show_session_includes_assigned_lecciones(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('SES-API-13', 'G13');
        $leccion = $this->createLeccion($programacion->curso_id, 1, 'Salvación');
        $sesion->lecciones()->sync([$leccion->id]);

        $this->getJson("/api/v1/sesiones/{$sesion->id}")
            ->assertOk()
            ->assertJsonPath('data.lecciones.0.id', $leccion->id)
            ->assertJsonPath('data.lecciones.0.orden', 1)
            ->assertJsonMissingPath('data.lecciones.0.pivot');
    }

    private function createLeccion(int $cursoId, int $orden, string $nombre): Leccion
    {
        return Leccion::query()->create([
            'curso_id' => $cursoId,
            'orden' => $orden,
            'nombre' => $nombre,
            'activo' => true,
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
