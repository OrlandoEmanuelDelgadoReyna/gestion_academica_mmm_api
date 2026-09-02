<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\EntregaTarea;
use App\Models\ProgramacionAcademica;
use App\Models\Tarea;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;
use App\Repositories\Eloquent\EloquentEntregaTareaRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class EntregaTareaApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake('local');
    }

    public function test_admin_lists_entregas(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-ADM');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.adm');
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $this->createEntrega($tarea, $matricula->id, 'Entrega admin');

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/entregas-tarea?tarea_id={$tarea->id}")
            ->assertOk()
            ->assertJsonPath('data.0.contenido', 'Entrega admin')
            ->assertJsonPath('data.0.archivo.disponible', false)
            ->assertJsonMissingPath('data.0.ruta_archivo');
    }

    public function test_assigned_docente_lists_entregas(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DOC-OK');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.doc');
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $this->createEntrega($tarea, $matricula->id, 'Entrega docente');
        $docente = $this->createDocenteUser('docente.ent.ok');
        $this->assignDocente($docente, $programacion);

        $this->getJson("/api/v1/entregas-tarea?tarea_id={$tarea->id}")
            ->assertOk()
            ->assertJsonPath('data.0.contenido', 'Entrega docente')
            ->assertJsonPath('data.0.alumno.nombre_completo', 'Alumno alumno.ent.doc');
    }

    public function test_unassigned_docente_cannot_list_entregas(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('ENT-DOC-X');
        $tarea = $this->createTarea($ajena, (int) $admin->id);
        $this->createDocenteUser('docente.ent.x');

        $this->getJson("/api/v1/entregas-tarea?tarea_id={$tarea->id}")
            ->assertForbidden();
    }

    public function test_alumno_can_view_own_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-ALU-OK');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.own');
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, 'Mi texto');

        Sanctum::actingAs($alumno);
        $this->getJson("/api/v1/entregas-tarea/{$entrega->id}")
            ->assertOk()
            ->assertJsonPath('data.contenido', 'Mi texto')
            ->assertJsonMissingPath('data.ruta_archivo');

        $ids = collect($this->getJson("/api/v1/entregas-tarea?tarea_id={$tarea->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($entrega->id));
        $this->assertCount(1, $ids);
    }

    public function test_alumno_cannot_view_companion_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-ALU-SPY');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $dueno = $this->createAlumnoUser('alumno.ent.dueno');
        $ajeno = $this->createAlumnoUser('alumno.ent.spy');
        $matricula = $this->enrollAlumno($dueno, $programacion);
        $this->enrollAlumno($ajeno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, 'Solo el dueño');

        Sanctum::actingAs($ajeno);
        $this->getJson("/api/v1/entregas-tarea/{$entrega->id}")
            ->assertForbidden();

        $ids = collect($this->getJson("/api/v1/entregas-tarea?tarea_id={$tarea->id}")
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertFalse($ids->contains($entrega->id));
    }

    public function test_alumno_can_create_own_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-ALU-C');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.cr');
        $this->enrollAlumno($alumno, $programacion);

        $response = $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Respuesta del alumno',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.contenido', 'Respuesta del alumno')
            ->assertJsonPath('data.tarea_id', $tarea->id)
            ->assertJsonMissingPath('data.ruta_archivo');

        $this->assertNotNull($response->json('data.entregado_at'));
        $this->assertDatabaseCount('entregas_tarea', 1);
    }

    public function test_alumno_cannot_use_foreign_matricula_id(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-ALU-FOR');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $dueno = $this->createAlumnoUser('alumno.ent.m1');
        $ajeno = $this->createAlumnoUser('alumno.ent.m2');
        $matriculaAjena = $this->enrollAlumno($dueno, $programacion);
        $this->enrollAlumno($ajeno, $programacion);

        Sanctum::actingAs($ajeno);
        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'matricula_id' => $matriculaAjena->id,
            'contenido' => 'Intento ajeno',
        ])->assertForbidden();

        $this->assertDatabaseCount('entregas_tarea', 0);
    }

    public function test_retirada_enrollment_cannot_create_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-RET');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.ret');
        $this->enrollAlumno($alumno, $programacion, 'retirada');

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Fuera',
        ])->assertForbidden();
    }

    public function test_completada_enrollment_cannot_create_entrega(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-COM');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.com');
        $this->enrollAlumno($alumno, $programacion, 'completada');

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Fuera',
        ])->assertForbidden();
    }

    public function test_tarea_of_other_programacion_is_forbidden(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->createProgramacion('ENT-OWN');
        $ajena = $this->createProgramacion('ENT-OTH');
        $secreta = $this->createTarea($ajena, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.oth');
        $this->enrollAlumno($alumno, $propia);

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $secreta->id,
            'contenido' => 'No debería',
        ])->assertForbidden();
    }

    public function test_duplicate_entrega_returns_422(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DUP');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.dup');
        $this->enrollAlumno($alumno, $programacion);

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Primera',
        ])->assertCreated();

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Segunda',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['tarea_id']);
    }

    public function test_entrega_after_deadline_returns_422(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DL');
        $tarea = $this->createTarea($programacion, (int) $admin->id, fechaLimite: now()->subDay());
        $alumno = $this->createAlumnoUser('alumno.ent.dl');
        $this->enrollAlumno($alumno, $programacion);

        $this->postJson('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Tarde',
            'entregado_at' => now()->subWeeks(2)->toDateTimeString(),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_limite_at']);
    }

    public function test_update_after_deadline_returns_422(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DLU');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.dlu');
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, 'A tiempo');
        $tarea->update(['fecha_limite_at' => now()->subDay()]);

        Sanctum::actingAs($alumno);
        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Después del plazo',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['fecha_limite_at']);
    }

    public function test_alumno_cannot_modify_nota(): void
    {
        $entrega = $this->seedOwnEntrega('alumno.ent.nota');

        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Sigue igual de texto',
            'nota' => 20,
        ])->assertOk()
            ->assertJsonPath('data.nota', null);

        $this->assertNull($entrega->refresh()->nota);
    }

    public function test_alumno_cannot_modify_retroalimentacion(): void
    {
        $entrega = $this->seedOwnEntrega('alumno.ent.retx');

        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Texto',
            'retroalimentacion' => 'No debería guardar',
        ])->assertOk()
            ->assertJsonPath('data.retroalimentacion', null);
    }

    public function test_alumno_cannot_modify_calificado_por(): void
    {
        $entrega = $this->seedOwnEntrega('alumno.ent.calp');

        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Texto',
            'calificado_por_usuario_id' => 1,
            'calificado_at' => now()->toDateTimeString(),
        ])->assertOk()
            ->assertJsonPath('data.calificado_at', null);

        $this->assertNull($entrega->refresh()->calificado_por_usuario_id);
    }

    public function test_graded_entrega_cannot_be_modified_by_alumno(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-GRA');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.gra');
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, 'Calificada', [
            'nota' => 15,
            'calificado_at' => now(),
            'calificado_por_usuario_id' => $admin->id,
        ]);

        Sanctum::actingAs($alumno);
        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Intento de cambio',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['contenido']);
    }

    public function test_allowed_file_is_accepted(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-PDF');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.pdf');
        $this->enrollAlumno($alumno, $programacion);

        $response = $this->post('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'contenido' => 'Con PDF',
            'archivo' => UploadedFile::fake()->create('ensayo.pdf', 120, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.archivo.disponible', true)
            ->assertJsonPath('data.archivo.nombre_original', 'ensayo.pdf')
            ->assertJsonMissingPath('data.ruta_archivo');

        $this->assertGreaterThan(0, $response->json('data.archivo.tamano_bytes'));
        $this->assertStringNotContainsString('storage/app/private', (string) $response->getContent());
        $this->assertStringNotContainsString('entregas-tarea/', (string) $response->getContent());
    }

    public function test_disallowed_file_extension_returns_422(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-EXE');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.exe');
        $this->enrollAlumno($alumno, $programacion);

        $this->post('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'archivo' => UploadedFile::fake()->create('setup.exe', 30, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_file_over_10mb_returns_422(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-BIG');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.big');
        $this->enrollAlumno($alumno, $programacion);

        $this->post('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'archivo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['archivo']);
    }

    public function test_authorized_user_can_download_file(): void
    {
        $entrega = $this->seedOwnEntregaWithFile('alumno.ent.dl1', 'mio.pdf');

        $this->get("/api/v1/entregas-tarea/{$entrega->id}/descargar")
            ->assertOk()
            ->assertHeaderContains('content-disposition', 'attachment');
    }

    public function test_alumno_cannot_download_companion_file(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DL-SPY');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $dueno = $this->createAlumnoUser('alumno.ent.dld');
        $ajeno = $this->createAlumnoUser('alumno.ent.dls');
        $this->enrollAlumno($dueno, $programacion);
        $this->enrollAlumno($ajeno, $programacion);

        Sanctum::actingAs($dueno);
        $id = $this->storeEntregaFile($tarea, 'secreto.pdf');

        Sanctum::actingAs($ajeno);
        $this->get("/api/v1/entregas-tarea/{$id}/descargar")
            ->assertForbidden();
    }

    public function test_assigned_docente_can_download(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DL-DOC');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.dldoc');
        $this->enrollAlumno($alumno, $programacion);
        $id = $this->storeEntregaFile($tarea, 'trabajo.pdf');
        $docente = $this->createDocenteUser('docente.ent.dl');
        $this->assignDocente($docente, $programacion);

        $this->get("/api/v1/entregas-tarea/{$id}/descargar")
            ->assertOk();
    }

    public function test_unassigned_docente_cannot_download(): void
    {
        $admin = $this->actingAsAdmin();
        $ajena = $this->createProgramacion('ENT-DL-DX');
        $tarea = $this->createTarea($ajena, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.dldx');
        $this->enrollAlumno($alumno, $ajena);
        $id = $this->storeEntregaFile($tarea, 'oculto.pdf');
        $this->createDocenteUser('docente.ent.dlx');

        $this->get("/api/v1/entregas-tarea/{$id}/descargar")
            ->assertForbidden();
    }

    public function test_admin_can_download(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-DL-ADM');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.dladm');
        $this->enrollAlumno($alumno, $programacion);
        $id = $this->storeEntregaFile($tarea, 'admin.pdf');

        Sanctum::actingAs($admin);
        $this->get("/api/v1/entregas-tarea/{$id}/descargar")
            ->assertOk();
    }

    public function test_api_does_not_expose_physical_path(): void
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-PATH');
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser('alumno.ent.path');
        $this->enrollAlumno($alumno, $programacion);
        $id = $this->storeEntregaFile($tarea, 'visible.pdf');

        $body = (string) $this->getJson("/api/v1/entregas-tarea/{$id}")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('ruta_archivo', $body);
        $this->assertStringNotContainsString('storage/app/private', $body);
        $this->assertStringNotContainsString('entregas-tarea/', $body);
        $this->assertArrayNotHasKey('ruta_archivo', $this->getJson("/api/v1/entregas-tarea/{$id}")->json('data'));
    }

    public function test_replacing_file_deletes_previous(): void
    {
        $entrega = $this->seedOwnEntregaWithFile('alumno.ent.rep', 'anterior.pdf');
        $oldPath = $entrega->ruta_archivo;
        Storage::disk('local')->assertExists($oldPath);

        $this->post("/api/v1/entregas-tarea/{$entrega->id}", [
            '_method' => 'PUT',
            'contenido' => 'Actualizado',
            'archivo' => UploadedFile::fake()->create('nuevo.pdf', 60, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Storage::disk('local')->assertMissing($oldPath);
        $updated = $entrega->refresh();
        $this->assertNotSame($oldPath, $updated->ruta_archivo);
        Storage::disk('local')->assertExists($updated->ruta_archivo);
        $this->assertSame('nuevo.pdf', $updated->nombre_original);
    }

    public function test_failed_save_keeps_previous_file(): void
    {
        $entrega = $this->seedOwnEntregaWithFile('alumno.ent.fail', 'conservar.pdf');
        $oldPath = $entrega->ruta_archivo;
        Storage::disk('local')->assertExists($oldPath);

        $inner = $this->app->make(EloquentEntregaTareaRepository::class);
        $this->app->bind(EntregaTareaRepositoryInterface::class, function () use ($inner) {
            return new class($inner) implements EntregaTareaRepositoryInterface
            {
                public function __construct(private EntregaTareaRepositoryInterface $inner) {}

                public function paginate(int $perPage, ?int $tareaId = null, ?int $assignedMiembroId = null, ?int $enrolledMiembroId = null): LengthAwarePaginator
                {
                    return $this->inner->paginate($perPage, $tareaId, $assignedMiembroId, $enrolledMiembroId);
                }

                public function create(array $data): EntregaTarea
                {
                    return $this->inner->create($data);
                }

                public function update(EntregaTarea $entrega, array $data): EntregaTarea
                {
                    throw new RuntimeException('simulated failure');
                }
            };
        });

        $this->post("/api/v1/entregas-tarea/{$entrega->id}", [
            '_method' => 'PUT',
            'contenido' => 'No debe persistir',
            'archivo' => UploadedFile::fake()->create('no-debe-quedar.pdf', 70, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(500);

        Storage::disk('local')->assertExists($oldPath);
        $this->assertSame($oldPath, $entrega->refresh()->ruta_archivo);
        $this->assertCount(1, Storage::disk('local')->allFiles('entregas-tarea'));
    }

    public function test_removing_file_keeps_text_only(): void
    {
        $entrega = $this->seedOwnEntregaWithFile('alumno.ent.txt', 'quitar.pdf');
        $oldPath = $entrega->ruta_archivo;

        $this->putJson("/api/v1/entregas-tarea/{$entrega->id}", [
            'contenido' => 'Solo texto',
            'eliminar_archivo' => true,
        ])->assertOk()
            ->assertJsonPath('data.archivo.disponible', false)
            ->assertJsonPath('data.contenido', 'Solo texto');

        Storage::disk('local')->assertMissing($oldPath);
        $this->assertNull($entrega->refresh()->ruta_archivo);
    }

    private function seedOwnEntrega(string $username, string $contenido = 'Texto propio'): EntregaTarea
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENT-'.substr(md5($username), 0, 6));
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser($username);
        $matricula = $this->enrollAlumno($alumno, $programacion);
        $entrega = $this->createEntrega($tarea, $matricula->id, $contenido);
        Sanctum::actingAs($alumno);

        return $entrega;
    }

    private function seedOwnEntregaWithFile(string $username, string $filename): EntregaTarea
    {
        $admin = $this->actingAsAdmin();
        $programacion = $this->createProgramacion('ENTF-'.substr(md5($username), 0, 5));
        $tarea = $this->createTarea($programacion, (int) $admin->id);
        $alumno = $this->createAlumnoUser($username);
        $this->enrollAlumno($alumno, $programacion);
        $id = $this->storeEntregaFile($tarea, $filename);

        return EntregaTarea::query()->findOrFail($id);
    }

    private function storeEntregaFile(Tarea $tarea, string $filename): int
    {
        return (int) $this->post('/api/v1/entregas-tarea', [
            'tarea_id' => $tarea->id,
            'archivo' => UploadedFile::fake()->create($filename, 80, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    /** @param array<string, mixed> $extra */
    private function createEntrega(Tarea $tarea, int $matriculaId, string $contenido, array $extra = []): EntregaTarea
    {
        return EntregaTarea::query()->create(array_merge([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matriculaId,
            'contenido' => $contenido,
            'entregado_at' => now(),
        ], $extra));
    }

    private function createTarea(
        ProgramacionAcademica $programacion,
        int $actorId,
        string $titulo = 'Tarea 1',
        mixed $fechaLimite = null,
    ): Tarea {
        return Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => $titulo,
            'descripcion' => 'Descripción',
            'publicado_at' => now(),
            'fecha_limite_at' => $fechaLimite ?? now()->addWeek(),
            'puntaje_maximo' => 20,
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
}
