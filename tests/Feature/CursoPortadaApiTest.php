<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Matricula;
use App\Models\ProgramacionAcademica;
use App\Support\CursoPortadaStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class CursoPortadaApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake(CursoPortadaStorage::DISK);
    }

    public function test_admin_can_create_curso_without_portada(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/cursos', [
            'iglesia_id' => $this->churchId(),
            'codigo' => 'POR-000',
            'nombre' => 'Sin portada',
            'activo' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.codigo', 'POR-000')
            ->assertJsonPath('data.portada_path', null)
            ->assertJsonPath('data.portada_url', null)
            ->assertJsonPath('data.programaciones_count', 0)
            ->assertJsonPath('data.matriculas_count', 0);

        $this->assertDatabaseHas('cursos', [
            'codigo' => 'POR-000',
            'portada_path' => null,
        ]);
    }

    public function test_admin_can_create_curso_with_portada(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/api/v1/cursos', [
            'iglesia_id' => $this->churchId(),
            'codigo' => 'POR-101',
            'nombre' => 'Con portada',
            'portada' => UploadedFile::fake()->image('portada.jpg', 1280, 720),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.codigo', 'POR-101');

        $path = $response->json('data.portada_path');
        $this->assertIsString($path);
        $this->assertTrue(CursoPortadaStorage::isManagedPath($path));
        Storage::disk(CursoPortadaStorage::DISK)->assertExists($path);
        $this->assertStringContainsString($path, (string) $response->json('data.portada_url'));
        $this->assertDatabaseHas('cursos', [
            'codigo' => 'POR-101',
            'portada_path' => $path,
        ]);
    }

    public function test_admin_can_update_curso_portada(): void
    {
        $this->actingAsAdmin();
        $curso = $this->createCurso('POR-201');

        $response = $this->put('/api/v1/cursos/'.$curso->id, [
            'nombre' => 'Nombre con portada',
            'portada' => UploadedFile::fake()->image('nueva.png', 800, 450),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre con portada');

        $path = $response->json('data.portada_path');
        $this->assertIsString($path);
        Storage::disk(CursoPortadaStorage::DISK)->assertExists($path);
        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'portada_path' => $path,
        ]);
    }

    public function test_replacing_portada_deletes_previous_file(): void
    {
        $this->actingAsAdmin();

        $created = $this->post('/api/v1/cursos', [
            'iglesia_id' => $this->churchId(),
            'codigo' => 'POR-301',
            'nombre' => 'Reemplazo',
            'portada' => UploadedFile::fake()->image('primera.jpg'),
        ], ['Accept' => 'application/json']);

        $created->assertCreated();
        $oldPath = $created->json('data.portada_path');
        Storage::disk(CursoPortadaStorage::DISK)->assertExists($oldPath);

        $updated = $this->put('/api/v1/cursos/'.$created->json('data.id'), [
            'portada' => UploadedFile::fake()->image('segunda.png'),
        ], ['Accept' => 'application/json']);

        $updated->assertOk();
        $newPath = $updated->json('data.portada_path');
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk(CursoPortadaStorage::DISK)->assertExists($newPath);
        Storage::disk(CursoPortadaStorage::DISK)->assertMissing($oldPath);
    }

    public function test_curso_without_portada_returns_null_path_and_url(): void
    {
        $this->actingAsAdmin();
        $curso = $this->createCurso('POR-401');

        $this->getJson('/api/v1/cursos/'.$curso->id)
            ->assertOk()
            ->assertJsonPath('data.portada_path', null)
            ->assertJsonPath('data.portada_url', null);
    }

    public function test_index_returns_programaciones_and_matriculas_counts(): void
    {
        $this->actingAsAdmin();
        $curso = $this->createCurso('POR-501');
        $other = $this->createCurso('POR-502');
        $progA = $this->createProgramacion($curso, 'A');
        $progB = $this->createProgramacion($curso, 'B');
        $this->createProgramacion($other, 'A');

        $miembroUno = $this->createMiembro('80000001');
        $miembroDos = $this->createMiembro('80000002');
        $this->createMatricula($progA, $miembroUno);
        $this->createMatricula($progA, $miembroDos);
        $this->createMatricula($progB, $miembroUno);

        $response = $this->getJson('/api/v1/cursos?per_page=50');
        $response->assertOk();

        $items = collect($response->json('data'));
        $counted = $items->firstWhere('codigo', 'POR-501');
        $emptyish = $items->firstWhere('codigo', 'POR-502');

        $this->assertSame(2, $counted['programaciones_count']);
        $this->assertSame(3, $counted['matriculas_count']);
        $this->assertSame(1, $emptyish['programaciones_count']);
        $this->assertSame(0, $emptyish['matriculas_count']);
    }

    public function test_counts_are_zero_when_curso_has_no_programaciones(): void
    {
        $this->actingAsAdmin();
        $this->createCurso('POR-503');

        $this->getJson('/api/v1/cursos?per_page=50')
            ->assertOk()
            ->assertJsonFragment([
                'codigo' => 'POR-503',
                'programaciones_count' => 0,
                'matriculas_count' => 0,
            ]);
    }

    public function test_counts_use_matricula_records_not_unique_people(): void
    {
        $this->actingAsAdmin();
        $curso = $this->createCurso('POR-504');
        $progA = $this->createProgramacion($curso, 'A');
        $progB = $this->createProgramacion($curso, 'B');
        $miembro = $this->createMiembro('80000003');
        $this->createMatricula($progA, $miembro);
        $this->createMatricula($progB, $miembro);

        $this->getJson('/api/v1/cursos/'.$curso->id)
            ->assertOk()
            ->assertJsonPath('data.programaciones_count', 2)
            ->assertJsonPath('data.matriculas_count', 2);
    }

    public function test_index_does_not_issue_n_plus_one_queries_for_counts(): void
    {
        $this->actingAsAdmin();

        foreach (['N1-A', 'N1-B', 'N1-C'] as $codigo) {
            $curso = $this->createCurso($codigo);
            $prog = $this->createProgramacion($curso, 'A');
            $this->createMatricula($prog, $this->createMiembro('81'.substr(md5($codigo), 0, 6)));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/v1/cursos?per_page=15')->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $standaloneMatriculas = $queries->filter(function (string $sql): bool {
            $normalized = strtolower($sql);

            return str_contains($normalized, 'from `matriculas`')
                && ! str_contains($normalized, '(select count(*) from `matriculas`');
        });
        $standaloneProgramaciones = $queries->filter(function (string $sql): bool {
            $normalized = strtolower($sql);

            return str_contains($normalized, 'from `programaciones_academicas`')
                && ! str_contains($normalized, '(select count(*) from `programaciones_academicas`')
                && ! str_contains($normalized, '(select count(*) from `matriculas` inner join `programaciones_academicas`');
        });

        $this->assertTrue($standaloneMatriculas->isEmpty(), $queries->implode("\n"));
        $this->assertTrue($standaloneProgramaciones->isEmpty(), $queries->implode("\n"));
    }

    public function test_docente_cannot_create_or_update_curso_portada(): void
    {
        $curso = $this->createCurso('POR-403');
        $this->createDocenteUser('docente.portada');

        $this->post('/api/v1/cursos', [
            'iglesia_id' => $this->churchId(),
            'codigo' => 'POR-404',
            'nombre' => 'No permitido',
            'portada' => UploadedFile::fake()->image('no.jpg'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->put('/api/v1/cursos/'.$curso->id, [
            'portada' => UploadedFile::fake()->image('tampoco.png'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseMissing('cursos', ['codigo' => 'POR-404']);
        $this->assertDatabaseHas('cursos', [
            'id' => $curso->id,
            'portada_path' => null,
        ]);
    }

    public function test_portada_rejects_non_image_files(): void
    {
        $this->actingAsAdmin();

        $this->post('/api/v1/cursos', [
            'iglesia_id' => $this->churchId(),
            'codigo' => 'POR-405',
            'nombre' => 'Archivo inválido',
            'portada' => UploadedFile::fake()->create('malware.exe', 30, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        $this->assertDatabaseMissing('cursos', ['codigo' => 'POR-405']);
    }

    private function churchId(): int
    {
        return (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
    }

    private function createCurso(string $codigo): Curso
    {
        return Curso::query()->create([
            'iglesia_id' => $this->churchId(),
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);
    }

    private function createProgramacion(Curso $curso, string $grupo): ProgramacionAcademica
    {
        return ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => $grupo,
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 40,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }

    private function createMiembro(string $documento): int
    {
        $now = now();

        return (int) DB::table('miembros')->insertGetId([
            'iglesia_id' => $this->churchId(),
            'tipo_documento' => 'DNI',
            'numero_documento' => $documento,
            'nombres' => 'Alumno',
            'apellidos' => $documento,
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'correo_electronico' => $documento.'@mmm.local',
            'telefono' => '988888888',
            'direccion' => 'Dirección alumno',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function createMatricula(ProgramacionAcademica $programacion, int $miembroId): Matricula
    {
        return Matricula::query()->create([
            'programacion_academica_id' => $programacion->id,
            'miembro_id' => $miembroId,
            'fecha_matricula' => now(),
            'estado' => 'activa',
        ]);
    }
}
