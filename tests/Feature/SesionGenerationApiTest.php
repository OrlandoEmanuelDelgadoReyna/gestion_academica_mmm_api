<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\ProgramacionAcademica;
use App\Models\ProgramacionHorario;
use App\Models\Sesion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class SesionGenerationApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_generates_sessions_from_date_range_and_weekly_slots(): void
    {
        $programacionId = $this->createProgramacionViaApi(
            'SES-GEN-1',
            '2026-09-01',
            '2026-09-30',
            [
                ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
                ['dia_semana' => 3, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ],
        );

        $response = $this->postJson("/api/v1/programaciones-academicas/{$programacionId}/sesiones/generar");

        $response->assertSuccessful()
            ->assertJsonPath('created', 9)
            ->assertJsonPath('skipped', 0)
            ->assertJsonPath('total', 9)
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('data.0.estado', 'programada')
            ->assertJsonPath('data.0.orden', 1)
            ->assertJsonPath('data.8.orden', 9);

        $this->assertStringStartsWith('2026-09-02T19:00:00', (string) $response->json('data.0.inicio_at'));
        $this->assertStringStartsWith('2026-09-30T19:00:00', (string) $response->json('data.8.inicio_at'));

        $this->assertDatabaseCount('sesiones', 9);
        $this->assertDatabaseHas('sesiones', [
            'programacion_academica_id' => $programacionId,
            'orden' => 1,
            'estado' => 'programada',
        ]);
        $this->assertDatabaseMissing('sesiones', [
            'programacion_academica_id' => $programacionId,
            'inicio_at' => '2026-09-01 19:00:00',
        ]);
    }

    public function test_generation_is_idempotent(): void
    {
        $programacionId = $this->createProgramacionViaApi(
            'SES-GEN-2',
            '2026-09-01',
            '2026-09-30',
            [
                ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
                ['dia_semana' => 3, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ],
        );

        $this->postJson("/api/v1/programaciones-academicas/{$programacionId}/sesiones/generar")
            ->assertSuccessful()
            ->assertJsonPath('created', 9);

        $second = $this->postJson("/api/v1/programaciones-academicas/{$programacionId}/sesiones/generar");

        $second->assertSuccessful()
            ->assertJsonPath('created', 0)
            ->assertJsonPath('skipped', 9)
            ->assertJsonPath('total', 9)
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseCount('sesiones', 9);
    }

    public function test_preserves_existing_session_and_continues_orden(): void
    {
        $programacionId = $this->createProgramacionViaApi(
            'SES-GEN-3',
            '2026-09-01',
            '2026-09-30',
            [
                ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
                ['dia_semana' => 3, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ],
        );

        Sesion::query()->create([
            'programacion_academica_id' => $programacionId,
            'orden' => 1,
            'inicio_at' => '2026-09-07 19:00:00',
            'fin_at' => '2026-09-07 21:00:00',
            'tema' => 'Tema manual',
            'estado' => 'realizada',
        ]);

        $response = $this->postJson("/api/v1/programaciones-academicas/{$programacionId}/sesiones/generar");

        $response->assertSuccessful()
            ->assertJsonPath('created', 8)
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('total', 9);

        $this->assertDatabaseCount('sesiones', 9);
        $this->assertDatabaseHas('sesiones', [
            'programacion_academica_id' => $programacionId,
            'orden' => 1,
            'tema' => 'Tema manual',
            'estado' => 'realizada',
            'inicio_at' => '2026-09-07 19:00:00',
        ]);
        $this->assertDatabaseHas('sesiones', [
            'programacion_academica_id' => $programacionId,
            'orden' => 2,
            'estado' => 'programada',
        ]);
        $this->assertSame(1, Sesion::query()->where('tema', 'Tema manual')->count());
    }

    public function test_creates_two_sessions_on_the_same_weekday_with_distinct_slots(): void
    {
        $programacionId = $this->createProgramacionViaApi(
            'SES-GEN-4',
            '2026-09-07',
            '2026-09-07',
            [
                ['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
                ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ],
        );

        $this->postJson("/api/v1/programaciones-academicas/{$programacionId}/sesiones/generar")
            ->assertSuccessful()
            ->assertJsonPath('created', 2)
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('sesiones', [
            'programacion_academica_id' => $programacionId,
            'inicio_at' => '2026-09-07 10:00:00',
            'fin_at' => '2026-09-07 12:00:00',
        ]);
        $this->assertDatabaseHas('sesiones', [
            'programacion_academica_id' => $programacionId,
            'inicio_at' => '2026-09-07 19:00:00',
            'fin_at' => '2026-09-07 21:00:00',
        ]);
    }

    public function test_rejects_generation_without_horarios(): void
    {
        $cursoId = $this->createCurso('SES-GEN-5');
        $programacion = ProgramacionAcademica::query()->create([
            'curso_id' => $cursoId,
            'periodo' => '2026-II',
            'grupo' => 'SIN-HOR',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'borrador',
        ]);

        $this->postJson("/api/v1/programaciones-academicas/{$programacion->id}/sesiones/generar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios']);

        $this->assertDatabaseCount('sesiones', 0);
    }

    public function test_rejects_generation_when_stored_horario_is_invalid(): void
    {
        $cursoId = $this->createCurso('SES-GEN-6');
        $programacion = ProgramacionAcademica::query()->create([
            'curso_id' => $cursoId,
            'periodo' => '2026-II',
            'grupo' => 'HOR-INV',
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'borrador',
        ]);

        ProgramacionHorario::query()->create([
            'programacion_academica_id' => $programacion->id,
            'dia_semana' => 1,
            'hora_inicio' => '21:00:00',
            'hora_fin' => '19:00:00',
        ]);

        $this->postJson("/api/v1/programaciones-academicas/{$programacion->id}/sesiones/generar")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios.0.hora_fin']);

        $this->assertDatabaseCount('sesiones', 0);
    }

    public function test_index_filters_sessions_by_programacion(): void
    {
        $firstId = $this->createProgramacionViaApi(
            'SES-GEN-7',
            '2026-09-07',
            '2026-09-07',
            [['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00']],
            'G7',
        );
        $secondId = $this->createProgramacionViaApi(
            'SES-GEN-8',
            '2026-09-09',
            '2026-09-09',
            [['dia_semana' => 3, 'hora_inicio' => '19:00', 'hora_fin' => '21:00']],
            'G8',
        );

        $this->postJson("/api/v1/programaciones-academicas/{$firstId}/sesiones/generar")->assertSuccessful();
        $this->postJson("/api/v1/programaciones-academicas/{$secondId}/sesiones/generar")->assertSuccessful();

        $this->getJson("/api/v1/sesiones?programacion_academica_id={$firstId}&per_page=100")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.programacion_academica_id', $firstId);
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     */
    private function createProgramacionViaApi(
        string $cursoCodigo,
        string $fechaInicio,
        string $fechaFin,
        array $horarios,
        string $grupo = 'A',
    ): int {
        $cursoId = $this->createCurso($cursoCodigo);

        $response = $this->postJson('/api/v1/programaciones-academicas', [
            'curso_id' => $cursoId,
            'periodo' => '2026-II',
            'grupo' => $grupo,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'capacidad' => 30,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 2,
            'horarios' => $horarios,
        ]);

        $response->assertSuccessful();

        return (int) $response->json('data.id');
    }

    private function createCurso(string $codigo): int
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

        return Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ])->id;
    }
}
