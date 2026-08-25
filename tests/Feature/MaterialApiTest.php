<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Material;
use App\Models\ProgramacionAcademica;
use App\Models\TipoMaterial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class MaterialApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
    }

    public function test_admin_can_list_materiales(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-ADM');
        $material = $this->createMaterial($programacion, (int) $admin->id, [
            'titulo' => 'Guía administrativa',
        ]);

        $ids = collect($this->getJson('/api/v1/materiales?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($material->id));
    }

    public function test_filter_by_programacion_returns_only_that_programacion_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('MAT-F1');
        $otra = $this->createProgramacion('MAT-F2');
        $propio = $this->createMaterial($propia, (int) $admin->id, ['titulo' => 'De la programación']);
        $ajeno = $this->createMaterial($otra, (int) $admin->id, ['titulo' => 'De otra']);

        $ids = collect($this->getJson("/api/v1/materiales?programacion_academica_id={$propia->id}&per_page=100")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($propio->id));
        $this->assertFalse($ids->contains($ajeno->id));
        $this->assertCount(1, $ids);
    }

    public function test_assigned_docente_can_list_materials_of_own_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-DOC');
        $material = $this->createMaterial($programacion, (int) $admin->id, [
            'titulo' => 'Material del docente',
        ]);

        $docente = $this->createDocenteUser();
        $this->assignDocente($docente, $programacion);

        $ids = collect($this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($material->id));
    }

    public function test_docente_cannot_list_materials_of_foreign_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('MAT-AJN');
        $this->createMaterial($ajena, (int) $admin->id);

        $this->createDocenteUser();

        $this->getJson("/api/v1/materiales?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
    }

    public function test_docente_can_view_material_of_own_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-SHW');
        $material = $this->createMaterial($programacion, (int) $admin->id, [
            'titulo' => 'Detalle propio',
        ]);

        $docente = $this->createDocenteUser();
        $this->assignDocente($docente, $programacion);

        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $material->id)
            ->assertJsonPath('data.titulo', 'Detalle propio');
    }

    public function test_docente_cannot_view_material_of_foreign_programacion(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('MAT-FRN');
        $material = $this->createMaterial($ajena, (int) $admin->id);

        $this->createDocenteUser();

        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertForbidden();
    }

    public function test_programacion_without_materials_returns_empty_list(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-EMP');

        $this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_material_resource_includes_curso_grupo_periodo_and_tipo(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-RES', 'B', '2026-I');
        $this->createMaterial($programacion, (int) $admin->id, [
            'titulo' => 'Apuntes',
            'descripcion' => 'Resumen de clase',
            'ruta_recurso' => 'https://ejemplo.test/apuntes.pdf',
            'tipo_material_id' => $this->tipoMaterialId('DOCUMENTO'),
        ]);

        $this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertOk()
            ->assertJsonPath('data.0.titulo', 'Apuntes')
            ->assertJsonPath('data.0.descripcion', 'Resumen de clase')
            ->assertJsonPath('data.0.ruta_recurso', 'https://ejemplo.test/apuntes.pdf')
            ->assertJsonPath('data.0.programacion_academica.grupo', 'B')
            ->assertJsonPath('data.0.programacion_academica.periodo', '2026-I')
            ->assertJsonPath('data.0.programacion_academica.curso.nombre', 'Curso MAT-RES')
            ->assertJsonPath('data.0.programacion_academica.curso.codigo', 'MAT-RES')
            ->assertJsonPath('data.0.tipo_material.codigo', 'DOCUMENTO')
            ->assertJsonPath('data.0.tipo_material.nombre', 'Documento');
    }

    public function test_index_does_not_lazy_load_relations(): void
    {
        $admin = $this->actingAsAdmin();
        $primera = $this->createProgramacion('MAT-N1');
        $segunda = $this->createProgramacion('MAT-N2');
        $this->createMaterial($primera, (int) $admin->id, ['titulo' => 'Uno']);
        $this->createMaterial($primera, (int) $admin->id, [
            'titulo' => 'Dos',
            'tipo_material_id' => $this->tipoMaterialId('VIDEO'),
        ]);
        $this->createMaterial($segunda, (int) $admin->id, [
            'titulo' => 'Tres',
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
        ]);

        Model::preventLazyLoading();

        try {
            $this->getJson('/api/v1/materiales?per_page=100')->assertOk();
            $this->getJson("/api/v1/materiales?programacion_academica_id={$primera->id}")->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_material_delete_route_does_not_exist(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('MAT-DEL');
        $material = $this->createMaterial($programacion, (int) $admin->id);

        $this->deleteJson("/api/v1/materiales/{$material->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('materiales', ['id' => $material->id]);
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

    private function createMaterial(ProgramacionAcademica $programacion, int $actorId, array $overrides = []): Material
    {
        return Material::query()->create(array_merge([
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId(),
            'titulo' => 'Guía de estudio',
            'descripcion' => 'Material de apoyo',
            'ruta_recurso' => 'https://ejemplo.test/guia.pdf',
            'publicado_at' => '2026-08-01 10:00:00',
            'creado_por_usuario_id' => $actorId,
        ], $overrides));
    }

    private function tipoMaterialId(string $codigo = 'DOCUMENTO'): int
    {
        $id = TipoMaterial::query()->where('codigo', $codigo)->value('id');

        $this->assertNotNull($id);

        return (int) $id;
    }
}
