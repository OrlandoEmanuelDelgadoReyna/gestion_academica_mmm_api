<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\CriterioEvaluacion;
use App\Models\Curso;
use App\Models\EntregaTarea;
use App\Models\ExamenFinal;
use App\Models\IntentoExamen;
use App\Models\Matricula;
use App\Models\Notificacion;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Tarea;
use App\Models\TipoCertificado;
use App\Models\Usuario;
use App\Services\CertificadoPdfGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class CertificadoApiTest extends TestCase
{
    use AuthenticatesApiUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        Storage::fake('local');
    }

    public function test_admin_can_list_certificates_of_own_church(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.list');
        $this->emitAsAdmin($admin, $context);

        $ids = collect($this->getJson('/api/v1/certificados')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertNotEmpty($ids);
        $this->assertStringNotContainsString('storage/app', json_encode($this->getJson('/api/v1/certificados')->json()));
    }

    public function test_admin_can_emit_academic_certificate_with_pdf(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.emit');

        $response = $this->emitAsAdmin($admin, $context);

        $response->assertCreated()
            ->assertJsonPath('data.estado', 'emitido')
            ->assertJsonPath('data.tiene_documento', true)
            ->assertJsonMissingPath('data.ruta_documento');

        $codigo = $response->json('data.codigo_verificacion');
        $this->assertNotEmpty($codigo);
        $this->assertMatchesRegularExpression('/^CERT-\d{4}-[A-Z0-9]{6}$/', $response->json('data.codigo_legible'));

        $certificado = Certificado::query()->findOrFail($response->json('data.id'));
        $this->assertNotNull($certificado->ruta_documento);
        $this->assertTrue(Storage::disk('local')->exists($certificado->ruta_documento));
        $this->assertStringStartsWith('certificados/', $certificado->ruta_documento);
        $pdf = Storage::disk('local')->get($certificado->ruta_documento);
        $this->assertNotFalse($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);

        $view = app(CertificadoPdfGenerator::class)->viewData($certificado);
        $this->assertStringStartsWith('data:image/png;base64,', $view['qr_data_uri']);
        $this->assertStringContainsString($codigo, app(CertificadoPdfGenerator::class)->verifyUrl($codigo));
    }

    public function test_docente_can_list_certificates_of_assigned_programacion_only(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->makeCertificableContext($admin, 'alumno.doc.ok', 'CERT-DOC-OK');
        $ajena = $this->makeCertificableContext($admin, 'alumno.doc.x', 'CERT-DOC-X');
        $mia = $this->emitAsAdmin($admin, $propia)->json('data.id');
        $otra = $this->emitAsAdmin($admin, $ajena)->json('data.id');

        $docente = $this->createDocenteUser('docente.cert');
        $this->assignDocente($docente, ProgramacionAcademica::query()->findOrFail($propia['programacion']->id));

        $ids = collect($this->getJson('/api/v1/certificados')
            ->assertOk()
            ->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mia));
        $this->assertFalse($ids->contains($otra));

        $this->getJson("/api/v1/certificados/{$otra}")->assertForbidden();
    }

    public function test_docente_cannot_emit_revoke_or_replace(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.doc.mut');
        $id = $this->emitAsAdmin($admin, $context)->json('data.id');

        $docente = $this->createDocenteUser('docente.cert.mut');
        $this->assignDocente($docente, $context['programacion']);

        $this->postJson('/api/v1/certificados/emitir', $this->emitPayload($context))->assertForbidden();
        $this->postJson("/api/v1/certificados/{$id}/revocar", ['motivo' => 'No'])->assertForbidden();
        $this->postJson("/api/v1/certificados/{$id}/reemplazar")->assertForbidden();
    }

    public function test_alumno_lists_only_own_certificates_and_cannot_view_foreign(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->makeCertificableContext($admin, 'alumno.own');
        $ajena = $this->makeCertificableContext($admin, 'alumno.spy');
        $ownId = $this->emitAsAdmin($admin, $propia)->json('data.id');
        $foreignId = $this->emitAsAdmin($admin, $ajena)->json('data.id');

        Sanctum::actingAs($propia['alumno']);

        $ids = collect($this->getJson('/api/v1/certificados')->assertOk()->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($ownId));
        $this->assertFalse($ids->contains($foreignId));

        $this->getJson("/api/v1/certificados/{$ownId}")->assertOk();
        $this->getJson("/api/v1/certificados/{$foreignId}")->assertForbidden();
        $this->getJson("/api/v1/certificados/{$foreignId}/descargar")->assertForbidden();
        $this->postJson('/api/v1/certificados/emitir', $this->emitPayload($propia))->assertForbidden();
    }

    public function test_eligibility_approved_and_attendance_at_least_80(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.el.ok', presentes: 4, totalSesiones: 5);

        $this->getJson($this->eligibilityUrl($context))
            ->assertOk()
            ->assertJsonPath('data.elegible', true)
            ->assertJsonPath('data.curso_aprobado', true)
            ->assertJsonPath('data.asistencia_cumple', true)
            ->assertJsonPath('data.asistencia_porcentaje', 80);
    }

    public function test_eligibility_rejected_when_failed_or_attendance_below_80(): void
    {
        $admin = $this->actingAsAdmin();
        $desaprobado = $this->makeCertificableContext($admin, 'alumno.el.fail', notaTarea: 2, notaExamen: 2, presentes: 5, totalSesiones: 5);
        $this->getJson($this->eligibilityUrl($desaprobado))
            ->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.curso_aprobado', false);

        $bajaAsistencia = $this->makeCertificableContext($admin, 'alumno.el.abs', presentes: 3, totalSesiones: 5);
        $this->getJson($this->eligibilityUrl($bajaAsistencia))
            ->assertOk()
            ->assertJsonPath('data.elegible', false)
            ->assertJsonPath('data.asistencia_cumple', false);
    }

    public function test_emit_recalculates_grade_before_issuing(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.recalc');

        Calificacion::query()->updateOrCreate(
            ['matricula_id' => $context['matricula']->id],
            [
                'promedio_tareas' => 5,
                'nota_examen_final' => 5,
                'nota_final' => 5,
                'estado' => 'desaprobada',
                'calculado_at' => now()->subDay(),
            ],
        );

        $this->emitAsAdmin($admin, $context)->assertCreated();

        $this->assertSame('aprobada', Calificacion::query()->where('matricula_id', $context['matricula']->id)->value('estado'));
    }

    public function test_duplicate_emitted_certificate_is_blocked(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.dup');
        $this->emitAsAdmin($admin, $context)->assertCreated();

        $this->postJson('/api/v1/certificados/emitir', $this->emitPayload($context))
            ->assertUnprocessable();
    }

    public function test_replace_revoked_is_blocked_when_another_is_emitted(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.rep.dup');
        $primeroId = $this->emitAsAdmin($admin, $context)->json('data.id');

        $this->postJson("/api/v1/certificados/{$primeroId}/revocar", ['motivo' => 'Corrección'])
            ->assertOk();
        $this->emitAsAdmin($admin, $context)->assertCreated();

        $this->postJson("/api/v1/certificados/{$primeroId}/reemplazar")
            ->assertUnprocessable();
        $this->assertSame(1, Certificado::query()->where('estado', 'emitido')->count());
    }

    public function test_authenticated_download_and_foreign_download_blocked(): void
    {
        $admin = $this->actingAsAdmin();
        $propia = $this->makeCertificableContext($admin, 'alumno.dl');
        $id = $this->emitAsAdmin($admin, $propia)->json('data.id');

        $download = $this->get("/api/v1/certificados/{$id}/descargar", ['Accept' => 'application/pdf']);
        $download->assertOk();
        $this->assertStringStartsWith('%PDF', $download->streamedContent());
        $this->assertStringNotContainsString('storage/app/private', $download->headers->get('content-disposition') ?? '');

        $spy = $this->createAlumnoUser('alumno.dl.spy');
        $this->enrollAlumno($spy, $propia['programacion']);
        Sanctum::actingAs($spy);
        $this->get("/api/v1/certificados/{$id}/descargar")->assertForbidden();
    }

    public function test_public_verification_valid_revoked_and_replaced(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.ver');
        $emit = $this->emitAsAdmin($admin, $context);
        $id = $emit->json('data.id');
        $codigo = $emit->json('data.codigo_verificacion');

        $this->getJson("/api/v1/certificados/verificar/{$codigo}")
            ->assertOk()
            ->assertJsonPath('valido', true)
            ->assertJsonPath('data.estado', 'emitido')
            ->assertJsonMissingPath('data.ruta_documento')
            ->assertJsonMissingPath('data.miembro.correo_electronico')
            ->assertJsonMissingPath('data.miembro.telefono');

        $this->postJson("/api/v1/certificados/{$id}/revocar", ['motivo' => 'Error en datos'])
            ->assertOk()
            ->assertJsonPath('data.estado', 'revocado');

        $this->getJson("/api/v1/certificados/verificar/{$codigo}")
            ->assertOk()
            ->assertJsonPath('valido', false)
            ->assertJsonPath('data.estado_validacion', 'revocado')
            ->assertJsonMissingPath('data.ruta_documento');

        $context2 = $this->makeCertificableContext($admin, 'alumno.rep');
        $emitidoId = $this->emitAsAdmin($admin, $context2)->json('data.id');
        $oldPath = Certificado::query()->findOrFail($emitidoId)->ruta_documento;
        $oldCodigo = Certificado::query()->findOrFail($emitidoId)->codigo_verificacion;

        $replace = $this->postJson("/api/v1/certificados/{$emitidoId}/reemplazar")->assertCreated();
        $this->assertSame('reemplazado', Certificado::query()->findOrFail($emitidoId)->estado);
        $this->assertTrue(Storage::disk('local')->exists($oldPath));
        $this->assertNotSame($oldPath, Certificado::query()->findOrFail($replace->json('data.id'))->ruta_documento);
        $this->assertTrue(Storage::disk('local')->exists(Certificado::query()->findOrFail($replace->json('data.id'))->ruta_documento));

        $this->getJson("/api/v1/certificados/verificar/{$oldCodigo}")
            ->assertOk()
            ->assertJsonPath('valido', false)
            ->assertJsonPath('data.estado_validacion', 'reemplazado');
    }

    public function test_emit_notifies_student(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.ntf');
        $this->emitAsAdmin($admin, $context)->assertCreated();

        $this->assertTrue(
            Notificacion::query()
                ->where('tipo', 'certificado')
                ->where('contenido', 'like', '%está disponible%')
                ->whereHas('destinatarios', fn ($q) => $q->where('usuario_id', $context['alumno']->id))
                ->exists(),
        );
    }

    public function test_cannot_emit_for_member_of_another_church(): void
    {
        $admin = $this->actingAsAdmin();
        $now = now();
        $churchId = DB::table('iglesias')->insertGetId([
            'codigo' => 'MMM-OTRA',
            'nombre' => 'Otra iglesia',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $miembroId = DB::table('miembros')->insertGetId([
            'iglesia_id' => $churchId,
            'tipo_documento' => 'DNI',
            'numero_documento' => '88888888',
            'nombres' => 'Otro',
            'apellidos' => 'Miembro',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'M',
            'correo_electronico' => 'otro@mmm.local',
            'telefono' => '911111111',
            'direccion' => 'Otra',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cursoId = DB::table('cursos')->insertGetId([
            'iglesia_id' => $churchId,
            'codigo' => 'OTRA-C',
            'nombre' => 'Curso otra',
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $programacionId = DB::table('programaciones_academicas')->insertGetId([
            'curso_id' => $cursoId,
            'periodo' => '2026-X',
            'grupo' => 'Z',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-01',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->postJson('/api/v1/certificados/emitir', [
            'miembro_id' => $miembroId,
            'tipo_certificado_id' => $this->tipoId(TipoCertificado::CODIGO_ACADEMICO),
            'programacion_academica_id' => $programacionId,
        ])->assertForbidden();
    }

    public function test_inactive_user_cannot_access_certificates(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.off');
        $this->emitAsAdmin($admin, $context);

        $context['alumno']->update(['activo' => false]);
        Sanctum::actingAs($context['alumno']->fresh());

        $this->getJson('/api/v1/certificados')->assertForbidden();
    }

    public function test_recommendation_type_skips_academic_eligibility(): void
    {
        $admin = $this->actingAsAdmin();
        $alumno = $this->createAlumnoUser('alumno.rec');
        $programacion = $this->createProgramacion('CERT-REC');
        $tipoId = $this->tipoId(TipoCertificado::CODIGO_RECOMENDACION);

        $this->actingAs($admin);
        $this->getJson('/api/v1/certificados/elegibilidad?'.http_build_query([
            'miembro_id' => $alumno->miembro_id,
            'programacion_academica_id' => $programacion->id,
            'tipo_certificado_id' => $tipoId,
        ]))
            ->assertOk()
            ->assertJsonPath('data.elegible', true)
            ->assertJsonPath('data.aplica_requisitos_academicos', false);

        $this->postJson('/api/v1/certificados/emitir', [
            'miembro_id' => $alumno->miembro_id,
            'tipo_certificado_id' => $tipoId,
            'programacion_academica_id' => $programacion->id,
            'destinatario' => 'Carta',
        ])->assertCreated()->assertJsonPath('data.estado', 'emitido');
    }

    public function test_pdf_failure_does_not_leave_emitted_certificate(): void
    {
        $admin = $this->actingAsAdmin();
        $context = $this->makeCertificableContext($admin, 'alumno.pdf.fail');

        $this->app->instance(CertificadoPdfGenerator::class, new class extends CertificadoPdfGenerator
        {
            public function store(Certificado $certificado): string
            {
                throw new RuntimeException('Fallo de PDF');
            }
        });

        $this->postJson('/api/v1/certificados/emitir', $this->emitPayload($context))->assertStatus(500);
        $this->assertSame(0, Certificado::query()->count());
    }

    /** @param  array{alumno: Usuario, programacion: ProgramacionAcademica, matricula: Matricula}  $context */
    private function emitAsAdmin(Usuario $admin, array $context)
    {
        $this->actingAs($admin);

        return $this->postJson('/api/v1/certificados/emitir', $this->emitPayload($context));
    }

    /** @param  array{alumno: Usuario, programacion: ProgramacionAcademica}  $context */
    private function emitPayload(array $context): array
    {
        return [
            'miembro_id' => $context['alumno']->miembro_id,
            'tipo_certificado_id' => $this->tipoId(TipoCertificado::CODIGO_ACADEMICO),
            'programacion_academica_id' => $context['programacion']->id,
            'destinatario' => $context['alumno']->miembro?->nombre_completo,
        ];
    }

    /** @param  array{alumno: Usuario, programacion: ProgramacionAcademica}  $context */
    private function eligibilityUrl(array $context): string
    {
        return '/api/v1/certificados/elegibilidad?'.http_build_query([
            'miembro_id' => $context['alumno']->miembro_id,
            'programacion_academica_id' => $context['programacion']->id,
            'tipo_certificado_id' => $this->tipoId(TipoCertificado::CODIGO_ACADEMICO),
        ]);
    }

    /**
     * @return array{alumno: Usuario, programacion: ProgramacionAcademica, matricula: Matricula}
     */
    private function makeCertificableContext(
        Usuario $admin,
        string $alumnoUsername,
        ?string $codigo = null,
        int $presentes = 4,
        int $totalSesiones = 5,
        float $notaTarea = 8,
        float $notaExamen = 8,
    ): array {
        $codigo ??= 'CERT-'.substr(md5($alumnoUsername), 0, 6);
        $programacion = $this->createProgramacion($codigo);
        $alumno = $this->createAlumnoUser($alumnoUsername);
        $matricula = $this->enrollAlumno($alumno, $programacion);

        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'codigo' => 'TAREAS',
            'origen' => 'tareas',
            'nombre' => 'Tareas',
            'porcentaje' => 40,
            'orden' => 1,
        ]);
        CriterioEvaluacion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'codigo' => 'EXAMEN',
            'origen' => 'examen_final',
            'nombre' => 'Examen final',
            'porcentaje' => 60,
            'orden' => 2,
        ]);

        $tarea = Tarea::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Tarea 1',
            'descripcion' => null,
            'publicado_at' => now(),
            'fecha_limite_at' => now()->addWeek(),
            'puntaje_maximo' => 10,
            'creado_por_usuario_id' => $admin->id,
        ]);
        EntregaTarea::query()->create([
            'tarea_id' => $tarea->id,
            'matricula_id' => $matricula->id,
            'contenido' => 'Entrega',
            'entregado_at' => now(),
            'nota' => $notaTarea,
            'calificado_at' => now(),
            'calificado_por_usuario_id' => $admin->id,
        ]);

        $examen = ExamenFinal::query()->create([
            'programacion_academica_id' => $programacion->id,
            'titulo' => 'Examen final',
            'descripcion' => null,
            'puntaje_maximo' => 10,
            'nota_minima_aprobatoria' => 11,
            'activo' => true,
        ]);
        IntentoExamen::query()->create([
            'examen_final_id' => $examen->id,
            'matricula_id' => $matricula->id,
            'inicio_at' => now()->subHour(),
            'fin_at' => now(),
            'estado' => 'completado',
            'puntaje_obtenido' => $notaExamen,
        ]);

        for ($i = 1; $i <= $totalSesiones; $i++) {
            $sesion = Sesion::query()->create([
                'programacion_academica_id' => $programacion->id,
                'orden' => $i,
                'inicio_at' => now()->subWeeks($totalSesiones - $i + 1),
                'fin_at' => now()->subWeeks($totalSesiones - $i + 1)->addHours(2),
                'tema' => "Sesión {$i}",
                'estado' => 'realizada',
            ]);
            if ($i <= $presentes) {
                Asistencia::query()->create([
                    'sesion_id' => $sesion->id,
                    'matricula_id' => $matricula->id,
                    'estado' => 'asistio',
                    'registrado_por_usuario_id' => $admin->id,
                ]);
            }
        }

        $this->actingAs($admin);

        return [
            'alumno' => $alumno->load('miembro'),
            'programacion' => $programacion,
            'matricula' => $matricula,
        ];
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
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-01',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);
    }

    private function tipoId(string $codigo): int
    {
        return (int) TipoCertificado::query()->where('codigo', $codigo)->value('id');
    }
}
