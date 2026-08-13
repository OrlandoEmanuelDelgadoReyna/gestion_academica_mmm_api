<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\ProgramacionHorario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

/**
 * Conflicto de horario en matrículas basado en programacion_horarios.
 *
 * Política explícita (destino sin horarios): si la programación destino no tiene
 * filas en programacion_horarios, la capa recurrente no bloquea la matrícula
 * (misma idea que el check legacy cuando no hay sesiones).
 */
final class MatriculaHorarioConflictApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    private int $cursoId;

    private int $alumnoId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
        $this->cursoId = $this->createCurso('MAT-HOR');
        $this->alumnoId = $this->createAlumno();
    }

    public function test_rejects_overlapping_recurring_schedules(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', [
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ]);

        $this->enroll($progA->id)->assertSuccessful();

        $response = $this->enroll($progB->id);
        $response->assertUnprocessable()
            ->assertJsonPath('code', 'HORARIO_CONFLICTO')
            ->assertJsonValidationErrors(['miembro_id'])
            ->assertJsonPath('conflicto_horario.grupo', 'A')
            ->assertJsonPath('conflicto_horario.dia_semana', 1)
            ->assertJsonPath('conflicto_horario.hora_inicio', '19:00')
            ->assertJsonPath('conflicto_horario.hora_fin', '21:00');

        $this->assertNotEmpty($response->json('conflicto_horario.curso'));
        $this->assertStringContainsString(
            'matrícula',
            strtolower((string) $response->json('errors.miembro_id.0')),
        );
        $this->assertStringNotContainsString(
            'solapado',
            strtolower((string) $response->json('errors.miembro_id.0')),
        );
    }

    public function test_allows_consecutive_schedules_same_day(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', [
            ['dia_semana' => 1, 'hora_inicio' => '21:00', 'hora_fin' => '23:00'],
        ]);

        $this->enroll($progA->id)->assertSuccessful();
        $this->enroll($progB->id)->assertSuccessful();
    }

    public function test_allows_same_time_on_different_days(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', [
            ['dia_semana' => 2, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);

        $this->enroll($progA->id)->assertSuccessful();
        $this->enroll($progB->id)->assertSuccessful();
    }

    public function test_allows_overlap_when_previous_matricula_is_retirada(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', [
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ]);

        $created = $this->enroll($progA->id)->assertSuccessful();
        Matricula::query()->whereKey($created->json('data.id'))->update(['estado' => 'retirada']);

        $this->enroll($progB->id)->assertSuccessful();
    }

    public function test_rejects_overlap_without_sesiones(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', [
            ['dia_semana' => 1, 'hora_inicio' => '20:00', 'hora_fin' => '22:00'],
        ]);

        $this->assertDatabaseCount('sesiones', 0);
        $this->assertDatabaseHas('programacion_horarios', [
            'programacion_academica_id' => $progA->id,
            'dia_semana' => 1,
        ]);

        $this->enroll($progA->id)->assertSuccessful();
        $this->enroll($progB->id)->assertUnprocessable();
    }

    public function test_allows_destination_without_horarios(): void
    {
        $progA = $this->createProgramacionAbierta('A', [
            ['dia_semana' => 1, 'hora_inicio' => '19:00', 'hora_fin' => '21:00'],
        ]);
        $progB = $this->createProgramacionAbierta('B', []);

        $this->assertDatabaseMissing('programacion_horarios', [
            'programacion_academica_id' => $progB->id,
        ]);

        $this->enroll($progA->id)->assertSuccessful();
        $this->enroll($progB->id)->assertSuccessful();
    }

    /** @param list<array{dia_semana: int, hora_inicio: string, hora_fin: string}> $horarios */
    private function createProgramacionAbierta(string $grupo, array $horarios): ProgramacionAcademica
    {
        $programacion = ProgramacionAcademica::query()->create([
            'curso_id' => $this->cursoId,
            'periodo' => '2026-II',
            'grupo' => $grupo,
            'fecha_inicio' => '2026-08-15',
            'fecha_fin' => '2026-10-15',
            'capacidad' => 30,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);

        foreach ($horarios as $slot) {
            ProgramacionHorario::query()->create([
                'programacion_academica_id' => $programacion->id,
                'dia_semana' => $slot['dia_semana'],
                'hora_inicio' => $slot['hora_inicio'].':00',
                'hora_fin' => $slot['hora_fin'].':00',
            ]);
        }

        return $programacion;
    }

    private function enroll(int $programacionId): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/matriculas', [
            'programacion_academica_id' => $programacionId,
            'miembro_id' => $this->alumnoId,
        ]);
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

    private function createAlumno(): int
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $estado = (int) DB::table('estados_membresia')->where('codigo', 'MIEMBRO')->value('id');
        $adminId = (int) DB::table('usuarios')->where('nombre_usuario', 'admin')->value('id');
        $now = now();

        $miembro = Miembro::query()->create([
            'iglesia_id' => $church,
            'tipo_documento' => 'DNI',
            'numero_documento' => '87654321',
            'nombres' => 'Alumno',
            'apellidos' => 'Prueba',
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'correo_electronico' => 'alumno.horario@mmm.local',
            'telefono' => '988888888',
            'direccion' => 'Dirección alumno',
        ]);

        DB::table('historial_membresia')->insert([
            'miembro_id' => $miembro->id,
            'estado_membresia_id' => $estado,
            'fecha_inicio' => '2024-01-01',
            'registrado_por_usuario_id' => $adminId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $miembro->id;
    }
}
