<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class MatriculaFilterApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->actingAsAdmin();
    }

    public function test_index_filters_by_programacion(): void
    {
        $first = $this->createProgramacionWithMatriculas('MAT-FIL-1', 'G1', 2);
        $second = $this->createProgramacionWithMatriculas('MAT-FIL-2', 'G2', 1);

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$first['programacion']->id}&per_page=100")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.programacion_academica_id', $first['programacion']->id)
            ->assertJsonPath('data.1.programacion_academica_id', $first['programacion']->id);

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$second['programacion']->id}&per_page=100")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.programacion_academica_id', $second['programacion']->id);
    }

    public function test_index_filters_by_programacion_and_activa_estado(): void
    {
        $context = $this->createProgramacionWithMatriculas('MAT-FIL-3', 'G3', 2);
        $context['matriculas'][1]->update(['estado' => 'retirada']);

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$context['programacion']->id}&estado=activa&per_page=100")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $context['matriculas'][0]->id)
            ->assertJsonPath('data.0.estado', 'activa');
    }

    public function test_index_without_filters_still_returns_matriculas(): void
    {
        $this->createProgramacionWithMatriculas('MAT-FIL-4', 'G4', 1);

        $this->getJson('/api/v1/matriculas')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    /**
     * @return array{programacion: ProgramacionAcademica, matriculas: list<Matricula>}
     */
    private function createProgramacionWithMatriculas(string $cursoCodigo, string $grupo, int $alumnos): array
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

        $matriculas = [];
        for ($i = 1; $i <= $alumnos; $i++) {
            $miembro = Miembro::query()->create([
                'iglesia_id' => $church,
                'tipo_documento' => 'DNI',
                'numero_documento' => sprintf('8%s%02d', substr(md5($cursoCodigo), 0, 6), $i),
                'nombres' => "Alumno{$i}",
                'apellidos' => $cursoCodigo,
                'fecha_nacimiento' => '2000-01-01',
                'sexo' => 'M',
                'correo_electronico' => "alumno{$i}.{$cursoCodigo}@mmm.local",
                'telefono' => '98888888'.$i,
                'direccion' => 'Dirección alumno',
            ]);

            $matriculas[] = Matricula::query()->create([
                'programacion_academica_id' => $programacion->id,
                'miembro_id' => $miembro->id,
                'fecha_matricula' => now(),
                'estado' => 'activa',
            ]);
        }

        return ['programacion' => $programacion, 'matriculas' => $matriculas];
    }
}
