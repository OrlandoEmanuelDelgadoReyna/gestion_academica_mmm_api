<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Material;
use App\Models\Matricula;
use App\Models\ProgramacionAcademica;
use App\Models\TipoMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class AlumnoMaterialAccessApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake('local');
    }

    public function test_enrolled_alumno_can_list_and_view_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-OK');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ok');
        $this->enrollAlumno($alumno, $programacion);

        $ids = collect($this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($material->id));

        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $material->id)
            ->assertJsonPath('data.titulo', $material->titulo);
    }

    public function test_enrolled_alumno_can_download_private_material(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-DL');
        $id = $this->storeDocumentoFile($programacion);
        $alumno = $this->createAlumnoUser('alumno.dl');
        $this->enrollAlumno($alumno, $programacion);

        $this->get("/api/v1/materiales/{$id}/descargar")
            ->assertOk()
            ->assertHeaderContains('content-disposition', 'attachment');
    }

    public function test_alumno_of_other_programacion_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('ALU-OWN');
        $ajena = $this->createProgramacion('ALU-OTH');
        $material = $this->createUrlMaterial($ajena, (int) $admin->id);
        $this->storeDocumentoFile($ajena, 'secreto.pdf');
        $fileId = (int) Material::query()->where('programacion_academica_id', $ajena->id)
            ->where('titulo', 'Archivo de prueba')
            ->value('id');

        $alumno = $this->createAlumnoUser('alumno.oth');
        $this->enrollAlumno($alumno, $propia);

        $this->getJson("/api/v1/materiales?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertForbidden();
        $this->get("/api/v1/materiales/{$fileId}/descargar")
            ->assertForbidden();
    }

    public function test_retirada_enrollment_cannot_access_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-RET');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ret');
        $this->enrollAlumno($alumno, $programacion, 'retirada');

        $this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertForbidden();
    }

    public function test_completada_enrollment_cannot_access_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-COM');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.com');
        $this->enrollAlumno($alumno, $programacion, 'completada');

        $this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertForbidden();
    }

    public function test_alumno_cannot_create_or_update_material(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-MUT');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.mut');
        $this->enrollAlumno($alumno, $programacion);

        $this->postJson('/api/v1/materiales', [
            'programacion_academica_id' => $programacion->id,
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'No debe crearse',
            'ruta_recurso' => 'https://ejemplo.test/no',
        ])->assertForbidden();

        $this->putJson("/api/v1/materiales/{$material->id}", [
            'titulo' => 'No debe editarse',
        ])->assertForbidden();
    }

    public function test_alumno_cannot_view_other_member_matriculas(): void
    {
        $propia = $this->createProgramacion('ALU-MAT-A');
        $ajena = $this->createProgramacion('ALU-MAT-B');
        $alumno = $this->createAlumnoUser('alumno.mat.a');
        $otro = $this->createAlumnoUser('alumno.mat.b');
        $mia = $this->enrollAlumno($alumno, $propia);
        $ajenaMatricula = $this->enrollAlumno($otro, $ajena);

        Sanctum::actingAs($alumno);

        $this->getJson("/api/v1/matriculas/{$ajenaMatricula->id}")
            ->assertForbidden();

        $ids = collect($this->getJson('/api/v1/matriculas?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mia->id));
        $this->assertFalse($ids->contains($ajenaMatricula->id));

        $this->getJson("/api/v1/matriculas?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
    }

    public function test_alumno_does_not_see_classmates_on_shared_programacion(): void
    {
        $programacion = $this->createProgramacion('ALU-SHARE');
        $alumno = $this->createAlumnoUser('alumno.share.a');
        $companero = $this->createAlumnoUser('alumno.share.b');
        $mia = $this->enrollAlumno($alumno, $programacion);
        $otra = $this->enrollAlumno($companero, $programacion);

        Sanctum::actingAs($alumno);

        $ids = collect($this->getJson("/api/v1/matriculas?programacion_academica_id={$programacion->id}&per_page=100")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mia->id));
        $this->assertFalse($ids->contains($otra->id));
        $this->assertCount(1, $ids);
    }

    public function test_admin_still_lists_all_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-ADM');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);

        $ids = collect($this->getJson('/api/v1/materiales?per_page=100')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($material->id));
    }

    public function test_assigned_docente_still_accesses_own_materials(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-DOC');
        $material = $this->createUrlMaterial($programacion, (int) $admin->id);
        $docente = $this->createDocenteUser('docente.alu');
        $this->assignDocente($docente, $programacion);

        $this->getJson("/api/v1/materiales?programacion_academica_id={$programacion->id}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $material->id);
        $this->getJson("/api/v1/materiales/{$material->id}")->assertOk();
    }

    public function test_unassigned_docente_still_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('ALU-DOC-X');
        $material = $this->createUrlMaterial($ajena, (int) $admin->id);
        $this->createDocenteUser('docente.alu.x');

        $this->getJson("/api/v1/materiales?programacion_academica_id={$ajena->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/materiales/{$material->id}")
            ->assertForbidden();
    }

    public function test_alumno_cannot_transition_programacion_or_issue_qr(): void
    {
        $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ALU-ADM2');
        $alumno = $this->createAlumnoUser('alumno.adm');
        $this->enrollAlumno($alumno, $programacion);

        $this->postJson("/api/v1/programaciones-academicas/{$programacion->id}/transiciones", [
            'estado' => 'en_curso',
        ])->assertForbidden();

        $this->postJson("/api/v1/programaciones-academicas/{$programacion->id}/sesiones/generar")
            ->assertForbidden();
    }

    private function storeDocumentoFile(ProgramacionAcademica $programacion, string $filename = 'guia.pdf'): int
    {
        $this->actingAsAdmin();

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
            'tipo_material_id' => $this->tipoMaterialId('ENLACE'),
            'titulo' => 'Guía de estudio',
            'descripcion' => 'Material de apoyo',
            'ruta_recurso' => 'https://ejemplo.test/guia.pdf',
            'publicado_at' => '2026-08-01 10:00:00',
            'creado_por_usuario_id' => $actorId,
        ]);
    }

    private function createProgramacion(string $codigo): ProgramacionAcademica
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
    }

    private function tipoMaterialId(string $codigo = 'DOCUMENTO'): int
    {
        $id = TipoMaterial::query()->where('codigo', $codigo)->value('id');
        $this->assertNotNull($id);

        return (int) $id;
    }
}
