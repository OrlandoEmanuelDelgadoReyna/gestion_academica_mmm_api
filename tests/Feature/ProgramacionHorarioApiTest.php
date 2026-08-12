<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class ProgramacionHorarioApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_create_programacion_with_one_horario(): void
    {
        $cursoId = $this->createCurso('HOR-1');

        $response = $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 6, 'hora_inicio' => '09:00', 'hora_fin' => '12:00'],
        ]));

        $response->assertSuccessful()
            ->assertJsonPath('data.horarios.0.dia_semana', 6)
            ->assertJsonPath('data.horarios.0.hora_inicio', '09:00')
            ->assertJsonPath('data.horarios.0.hora_fin', '12:00');

        $this->assertDatabaseHas('programacion_horarios', [
            'programacion_academica_id' => $response->json('data.id'),
            'dia_semana' => 6,
        ]);
    }

    public function test_create_programacion_with_multiple_horarios(): void
    {
        $cursoId = $this->createCurso('HOR-2');

        $response = $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 3, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]));

        $response->assertSuccessful()
            ->assertJsonCount(2, 'data.horarios');
    }

    public function test_rejects_invalid_dia_semana(): void
    {
        $cursoId = $this->createCurso('HOR-3');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 8, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios.0.dia_semana']);
    }

    public function test_rejects_hora_fin_not_after_inicio(): void
    {
        $cursoId = $this->createCurso('HOR-4');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '18:00'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios.0.hora_fin']);
    }

    public function test_rejects_duplicate_horario(): void
    {
        $cursoId = $this->createCurso('HOR-5');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios.1']);
    }

    public function test_rejects_internal_overlapping_horarios(): void
    {
        $cursoId = $this->createCurso('HOR-6');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios']);
    }

    public function test_allows_consecutive_horarios_same_day(): void
    {
        $cursoId = $this->createCurso('HOR-7');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 1, 'hora_inicio' => '21:00', 'hora_fin' => '23:00'],
        ]))->assertSuccessful()
            ->assertJsonCount(2, 'data.horarios');
    }

    public function test_allows_same_time_on_different_days(): void
    {
        $cursoId = $this->createCurso('HOR-8');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
            ['dia_semana' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]))->assertSuccessful()
            ->assertJsonCount(2, 'data.horarios');
    }

    public function test_detects_docente_schedule_conflict_across_programaciones(): void
    {
        $cursoId = $this->createCurso('HOR-9');
        $docenteId = (int) DB::table('miembros')->where('correo_electronico', 'admin@mmm.local')->value('id');

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ], [$docenteId], 'A'))->assertSuccessful();

        $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ], [$docenteId], 'B'))->assertUnprocessable()
            ->assertJsonValidationErrors(['horarios']);
    }

    public function test_update_replaces_horarios_transactionally(): void
    {
        $cursoId = $this->createCurso('HOR-10');

        $created = $this->postJson('/api/v1/programaciones-academicas', $this->basePayload($cursoId, [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]))->assertSuccessful();

        $id = $created->json('data.id');

        $this->putJson("/api/v1/programaciones-academicas/{$id}", [
            'horarios' => [
                ['dia_semana' => 3, 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
                ['dia_semana' => 5, 'hora_inicio' => '10:00', 'hora_fin' => '12:00'],
            ],
        ])->assertSuccessful()
            ->assertJsonCount(2, 'data.horarios')
            ->assertJsonPath('data.horarios.0.dia_semana', 3);

        $this->assertDatabaseMissing('programacion_horarios', [
            'programacion_academica_id' => $id,
            'dia_semana' => 1,
        ]);
        $this->assertDatabaseCount('programacion_horarios', 2);
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

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     * @param  list<int>  $docenteIds
     * @return array<string, mixed>
     */
    private function basePayload(int $cursoId, array $horarios, array $docenteIds = [], string $grupo = 'A'): array
    {
        return [
            'curso_id' => $cursoId,
            'periodo' => '2026-II',
            'grupo' => $grupo,
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-10-15',
            'capacidad' => 30,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 2,
            'docente_ids' => $docenteIds,
            'horarios' => $horarios,
        ];
    }
}
