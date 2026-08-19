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

final class LeccionApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_admin_can_create_leccion(): void
    {
        $curso = $this->createCurso('LEC-A');

        $response = $this->postJson('/api/v1/lecciones', [
            'curso_id' => $curso->id,
            'orden' => 1,
            'nombre' => 'Salvación',
            'descripcion' => 'Primera lección',
            'activo' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.nombre', 'Salvación')
            ->assertJsonPath('data.orden', 1)
            ->assertJsonPath('data.curso_id', $curso->id)
            ->assertJsonPath('data.activo', true)
            ->assertJsonMissingPath('data.pivot');

        $this->assertDatabaseHas('lecciones', [
            'curso_id' => $curso->id,
            'orden' => 1,
            'nombre' => 'Salvación',
        ]);
    }

    public function test_admin_can_update_leccion(): void
    {
        $curso = $this->createCurso('LEC-B');
        $leccion = $this->createLeccion($curso, 1, 'Nombre original');

        $this->putJson("/api/v1/lecciones/{$leccion->id}", [
            'nombre' => 'Arrepentimiento',
            'orden' => 2,
            'activo' => false,
        ])->assertOk()
            ->assertJsonPath('data.nombre', 'Arrepentimiento')
            ->assertJsonPath('data.orden', 2)
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('lecciones', [
            'id' => $leccion->id,
            'nombre' => 'Arrepentimiento',
            'orden' => 2,
            'activo' => false,
        ]);
    }

    public function test_duplicate_orden_in_same_curso_is_unprocessable(): void
    {
        $curso = $this->createCurso('LEC-C');
        $this->createLeccion($curso, 1, 'Salvación');

        $this->postJson('/api/v1/lecciones', [
            'curso_id' => $curso->id,
            'orden' => 1,
            'nombre' => 'Otra',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['orden']);
    }

    public function test_update_rejects_curso_id_change(): void
    {
        $curso = $this->createCurso('LEC-D1');
        $otro = $this->createCurso('LEC-D2');
        $leccion = $this->createLeccion($curso, 1, 'Fe');

        $this->putJson("/api/v1/lecciones/{$leccion->id}", [
            'curso_id' => $otro->id,
            'nombre' => 'Fe',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['curso_id']);

        $this->assertDatabaseHas('lecciones', [
            'id' => $leccion->id,
            'curso_id' => $curso->id,
        ]);
    }

    public function test_index_requires_curso_id(): void
    {
        $this->getJson('/api/v1/lecciones')->assertUnprocessable()
            ->assertJsonValidationErrors(['curso_id']);
    }

    public function test_admin_lists_lecciones_for_curso_ordered(): void
    {
        $curso = $this->createCurso('LEC-E');
        $this->createLeccion($curso, 2, 'Arrepentimiento');
        $this->createLeccion($curso, 1, 'Salvación');
        $otro = $this->createCurso('LEC-E2');
        $this->createLeccion($otro, 1, 'De otro curso');

        $nombres = collect($this->getJson("/api/v1/lecciones?curso_id={$curso->id}&per_page=100")
            ->assertOk()
            ->json('data'))->pluck('nombre')->all();

        $this->assertSame(['Salvación', 'Arrepentimiento'], $nombres);
    }

    public function test_cursos_index_does_not_embed_lecciones(): void
    {
        $curso = $this->createCurso('LEC-F');
        $this->createLeccion($curso, 1, 'Salvación');

        $this->getJson('/api/v1/cursos?per_page=100')
            ->assertOk()
            ->assertJsonMissingPath('data.0.lecciones');
    }

    public function test_leccion_delete_route_does_not_exist(): void
    {
        $curso = $this->createCurso('LEC-G');
        $leccion = $this->createLeccion($curso, 1, 'Salvación');

        $this->deleteJson("/api/v1/lecciones/{$leccion->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('lecciones', ['id' => $leccion->id]);
    }

    public function test_deactivating_leccion_keeps_sesion_assignment(): void
    {
        [$programacion, $sesion] = $this->createProgramacionWithSesion('LEC-H');
        $leccion = $this->createLeccion($programacion->curso, 1, 'Salvación');
        $sesion->lecciones()->sync([$leccion->id]);

        $this->putJson("/api/v1/lecciones/{$leccion->id}", [
            'activo' => false,
        ])->assertOk()
            ->assertJsonPath('data.activo', false);

        $this->assertDatabaseHas('sesion_lecciones', [
            'sesion_id' => $sesion->id,
            'leccion_id' => $leccion->id,
        ]);

        $this->getJson("/api/v1/sesiones/{$sesion->id}")
            ->assertOk()
            ->assertJsonPath('data.lecciones.0.id', $leccion->id)
            ->assertJsonPath('data.lecciones.0.activo', false)
            ->assertJsonMissingPath('data.lecciones.0.pivot');
    }

    private function createCurso(string $codigo): Curso
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

        return Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);
    }

    private function createLeccion(Curso $curso, int $orden, string $nombre): Leccion
    {
        return Leccion::query()->create([
            'curso_id' => $curso->id,
            'orden' => $orden,
            'nombre' => $nombre,
            'activo' => true,
        ]);
    }

    /** @return array{0: ProgramacionAcademica, 1: Sesion} */
    private function createProgramacionWithSesion(string $cursoCodigo): array
    {
        $curso = $this->createCurso($cursoCodigo);
        $programacion = ProgramacionAcademica::query()->create([
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
