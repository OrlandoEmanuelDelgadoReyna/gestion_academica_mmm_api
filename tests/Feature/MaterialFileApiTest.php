<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Material;
use App\Models\ProgramacionAcademica;
use App\Models\TipoMaterial;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Repositories\Eloquent\EloquentMaterialRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class MaterialFileApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake('local');
    }

    public function test_tipos_material_returns_active_documento_video_enlace(): void
    {
        $this->actingAsAdmin();

        $codigos = collect($this->getJson('/api/v1/tipos-material')
            ->assertOk()
            ->json('data'))->pluck('codigo')->all();

        $this->assertEqualsCanonicalizing(['DOCUMENTO', 'VIDEO', 'ENLACE'], $codigos);
        $this->assertTrue(collect($this->getJson('/api/v1/tipos-material')->json('data'))->every(
            fn (array $tipo) => $tipo['activo'] === true,
        ));
    }

    public function test_inactive_tipos_material_are_hidden(): void
    {
        $this->actingAsAdmin();
        TipoMaterial::query()->where('codigo', 'ENLACE')->update(['activo' => false]);

        $codigos = collect($this->getJson('/api/v1/tipos-material')
            ->assertOk()
            ->json('data'))->pluck('codigo')->all();

        $this->assertContains('DOCUMENTO', $codigos);
        $this->assertContains('VIDEO', $codigos);
        $this->assertNotContains('ENLACE', $codigos);
    }

    public function test_admin_creates_documento_with_valid_file(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-PDF');

        $response = $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Guía PDF',
            'archivo' => UploadedFile::fake()->create('guia.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.titulo', 'Guía PDF')
            ->assertJsonPath('data.es_url', false)
            ->assertJsonPath('data.recurso_privado', true)
            ->assertJsonPath('data.ruta_recurso', null)
            ->assertJsonPath('data.tipo_material.codigo', 'DOCUMENTO');

        $this->assertNotNull($response->json('data.tamano_bytes'));

        $material = Material::query()->findOrFail($response->json('data.id'));
        $this->assertSame($admin->id, $material->creado_por_usuario_id);
        Storage::disk('local')->assertExists($material->ruta_recurso);
    }

    public function test_admin_creates_documento_with_valid_url(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-URL');

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Guía en línea',
            'ruta_recurso' => 'https://ejemplo.test/guia.pdf',
        ])->assertCreated()
            ->assertJsonPath('data.es_url', true)
            ->assertJsonPath('data.recurso_privado', false)
            ->assertJsonPath('data.ruta_recurso', 'https://ejemplo.test/guia.pdf');
    }

    public function test_admin_creates_video_with_valid_url(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-VID');

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('VIDEO'),
            'titulo' => 'Clase grabada',
            'ruta_recurso' => 'https://youtube.com/watch?v=abc',
        ])->assertCreated()
            ->assertJsonPath('data.tipo_material.codigo', 'VIDEO')
            ->assertJsonPath('data.es_url', true);
    }

    public function test_creating_video_with_file_is_unprocessable(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-VF');

        $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('VIDEO'),
            'titulo' => 'Video subido',
            'archivo' => UploadedFile::fake()->create('clase.pdf', 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_admin_creates_enlace_with_valid_url(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-LNK');

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'Recurso web',
            'ruta_recurso' => 'http://ejemplo.test/recurso',
        ])->assertCreated()
            ->assertJsonPath('data.tipo_material.codigo', 'ENLACE')
            ->assertJsonPath('data.es_url', true);
    }

    public function test_creating_enlace_with_file_is_unprocessable(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-LF');

        $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'Enlace con archivo',
            'archivo' => UploadedFile::fake()->create('nota.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_ftp_url_is_unprocessable(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-FTP');

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'FTP',
            'ruta_recurso' => 'ftp://files.ejemplo.test/guia.pdf',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['ruta_recurso']);
    }

    public function test_disallowed_mime_or_extension_is_unprocessable(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-EXE');

        $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Ejecutable',
            'archivo' => UploadedFile::fake()->create('setup.exe', 30, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_file_larger_than_10mb_is_unprocessable(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-BIG');

        $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Demasiado grande',
            'archivo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_assigned_docente_creates_material_for_own_programacion(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-DC');
        $docente = $this->createDocenteUser();
        $this->assignDocente($docente, $programacion);

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Material del docente',
            'ruta_recurso' => 'https://ejemplo.test/docente.pdf',
        ])->assertCreated()
            ->assertJsonPath('data.titulo', 'Material del docente');
    }

    public function test_docente_cannot_create_material_for_foreign_programacion(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->createProgramacion('MAT-DCF');
        $this->createDocenteUser();

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $ajena->id,
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'Ajeno',
            'ruta_recurso' => 'https://ejemplo.test/ajeno',
        ])->assertForbidden();
    }

    public function test_assigned_docente_updates_own_material(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-DU');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $docente = $this->createDocenteUser();
        $this->assignDocente($docente, $programacion);

        $this->putJson("/api/v1/materiales/{$material->id}", [
            'titulo' => 'Título actualizado',
        ])->assertOk()
            ->assertJsonPath('data.titulo', 'Título actualizado');
    }

    public function test_docente_cannot_update_foreign_material(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('MAT-DUF');
        $material = $this->createUrlMaterial($ajena, (int) $admin->id);
        $this->createDocenteUser();

        $this->putJson("/api/v1/materiales/{$material->id}", [
            'titulo' => 'No debe pasar',
        ])->assertForbidden();
    }

    public function test_admin_can_create_and_update_material(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-ADM2');

        $id = $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'Creado por admin',
            'ruta_recurso' => 'https://ejemplo.test/admin',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/materiales/{$id}", [
            'titulo' => 'Editado por admin',
            'ruta_recurso' => 'https://ejemplo.test/admin-2',
        ])->assertOk()
            ->assertJsonPath('data.titulo', 'Editado por admin')
            ->assertJsonPath('data.ruta_recurso', 'https://ejemplo.test/admin-2');
    }

    public function test_authorized_user_can_download_private_material(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-DL');
        $id = $this->storeDocumentoFile($programacion, 'descarga.pdf');

        $this->get("/api/v1/materiales/{$id}/descargar")
            ->assertOk()
            ->assertHeaderContains('content-disposition', 'attachment');
    }

    public function test_docente_cannot_download_foreign_material(): void
    {
        $this->actingAsAdmin();
        $ajena = $this->createProgramacion('MAT-DLF');
        $id = $this->storeDocumentoFile($ajena, 'secreto.pdf');
        $this->createDocenteUser();

        $this->get("/api/v1/materiales/{$id}/descargar")->assertForbidden();
    }

    public function test_unauthenticated_download_is_unauthorized(): void
    {
        $adminId = (int) \App\Models\Usuario::query()->where('nombre_usuario', 'admin')->value('id');
        $programacion = $this->createProgramacion('MAT-DLU');
        Storage::disk('local')->put('materiales/privado.pdf', 'contenido');
        $material = Material::query()->create([
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Privado',
            'ruta_recurso' => 'materiales/privado.pdf',
            'creado_por_usuario_id' => $adminId,
        ]);

        $this->getJson("/api/v1/materiales/{$material->id}/descargar")
            ->assertUnauthorized();
    }

    public function test_replacing_file_deletes_previous_private_file(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-REP');
        $id = $this->storeDocumentoFile($programacion, 'anterior.pdf');
        $oldPath = Material::query()->findOrFail($id)->ruta_recurso;
        Storage::disk('local')->assertExists($oldPath);

        $this->post("/api/v1/materiales/{$id}", [
            '_method' => 'PUT',
            'archivo' => UploadedFile::fake()->create('nuevo.pdf', 60, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('local')->assertMissing($oldPath);
        $updated = Material::query()->findOrFail($id);
        $this->assertNotSame($oldPath, $updated->ruta_recurso);
        Storage::disk('local')->assertExists($updated->ruta_recurso);
    }

    public function test_changing_file_to_url_deletes_previous_private_file(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-URLR');
        $id = $this->storeDocumentoFile($programacion, 'archivo.pdf');
        $oldPath = Material::query()->findOrFail($id)->ruta_recurso;

        $this->putJson("/api/v1/materiales/{$id}", [
            'ruta_recurso' => 'https://ejemplo.test/ahora-enlace.pdf',
        ])->assertOk()
            ->assertJsonPath('data.es_url', true)
            ->assertJsonPath('data.ruta_recurso', 'https://ejemplo.test/ahora-enlace.pdf');

        Storage::disk('local')->assertMissing($oldPath);
    }

    public function test_failed_replacement_keeps_previous_file(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-FAIL');
        $id = $this->storeDocumentoFile($programacion, 'conservar.pdf');
        $oldPath = Material::query()->findOrFail($id)->ruta_recurso;

        $inner = $this->app->make(EloquentMaterialRepository::class);
        $this->app->bind(MaterialRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements MaterialRepositoryInterface
            {
                public function __construct(private MaterialRepositoryInterface $inner) {}

                public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?int $assignedMiembroId = null): LengthAwarePaginator
                {
                    return $this->inner->paginate($perPage, $programacionAcademicaId, $assignedMiembroId);
                }

                public function create(array $data): Material
                {
                    return $this->inner->create($data);
                }

                public function update(Material $material, array $data): Material
                {
                    throw new RuntimeException('simulated failure');
                }
            };
        });

        $this->post("/api/v1/materiales/{$id}", [
            '_method' => 'PUT',
            'archivo' => UploadedFile::fake()->create('no-debe-quedar.pdf', 70, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(500);

        Storage::disk('local')->assertExists($oldPath);
        $this->assertSame($oldPath, Material::query()->findOrFail($id)->ruta_recurso);
        $this->assertCount(1, Storage::disk('local')->allFiles('materiales'));
    }

    private function storeDocumentoFile(ProgramacionAcademica $programacion, string $filename): int
    {
        return (int) $this->post('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Archivo de prueba',
            'archivo' => UploadedFile::fake()->create($filename, 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    private function createUrlMaterial(ProgramacionAcademica $programacion, int $actorId): Material
    {
        return Material::query()->create([
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
            'titulo' => 'Guía de estudio',
            'descripcion' => 'Material de apoyo',
            'ruta_recurso' => 'https://ejemplo.test/guia.pdf',
            'publicado_at' => '2026-08-01 10:00:00',
            'creado_por_usuario_id' => $actorId,
        ]);
    }

    private function createProgramacion(string $codigo, string $grupo = 'A', string $periodo = '2026-II'): ProgramacionAcademica
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');

        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);

        return ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => $periodo,
            'grupo' => $grupo,
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }

    private function tipoMaterialId(string $codigo = 'DOCUMENTO'): int
    {
        $id = TipoMaterial::query()->where('codigo', $codigo)->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }
}
