<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\Sesion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class CertificadoApiTest extends TestCase
{
    use AuthenticatesApiUsers;

    public function test_emitir_and_public_verification_flow(): void
    {
        $this->actingAsCertificador();
        $context = $this->createCertificableContext();

        $emitResponse = $this->postJson('/api/v1/certificados/emitir', [
            'miembro_id' => $context['miembro_id'],
            'tipo_certificado_id' => $context['tipo_certificado_id'],
            'programacion_academica_id' => $context['programacion_id'],
            'destinatario' => 'Estudiante Test',
        ]);

        $emitResponse->assertCreated()
            ->assertJsonPath('data.estado', 'emitido')
            ->assertJsonStructure(['data' => ['codigo_verificacion']]);

        $codigo = $emitResponse->json('data.codigo_verificacion');

        $this->getJson("/api/v1/certificados/verificar/{$codigo}")
            ->assertOk()
            ->assertJsonPath('valido', true)
            ->assertJsonPath('data.estado', 'emitido');
    }

    public function test_revocar_and_reemplazar_certificate(): void
    {
        $this->actingAsCertificador();
        $context = $this->createCertificableContext();

        $certificado = Certificado::query()->create([
            'miembro_id' => $context['miembro_id'],
            'tipo_certificado_id' => $context['tipo_certificado_id'],
            'programacion_academica_id' => $context['programacion_id'],
            'codigo_verificacion' => (string) Str::uuid(),
            'emitido_at' => now(),
            'estado' => 'emitido',
            'emitido_por_usuario_id' => $context['usuario_id'],
        ]);

        $this->postJson("/api/v1/certificados/{$certificado->id}/revocar", [
            'motivo' => 'Error en datos',
        ])->assertOk()->assertJsonPath('data.estado', 'revocado');

        $emitido = Certificado::query()->create([
            'miembro_id' => $context['miembro_id'],
            'tipo_certificado_id' => $context['tipo_certificado_id'],
            'programacion_academica_id' => $context['programacion_id'],
            'codigo_verificacion' => (string) Str::uuid(),
            'emitido_at' => now(),
            'estado' => 'emitido',
            'emitido_por_usuario_id' => $context['usuario_id'],
        ]);

        $replaceResponse = $this->postJson("/api/v1/certificados/{$emitido->id}/reemplazar", [
            'destinatario' => 'Estudiante Corregido',
        ]);

        $replaceResponse->assertCreated()
            ->assertJsonPath('data.estado', 'emitido')
            ->assertJsonPath('data.certificado_reemplazado_id', $emitido->id);

        $this->assertSame('reemplazado', $emitido->fresh()->estado);
    }

    /** @return array{miembro_id: int, programacion_id: int, tipo_certificado_id: int, usuario_id: int} */
    private function createCertificableContext(): array
    {
        $this->seedInstitutionalCatalog();

        $usuarioId = DB::table('usuarios')->where('nombre_usuario', 'admin')->value('id');
        $member = DB::table('miembros')->where('correo_electronico', 'admin@mmm.local')->value('id');
        $church = DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $tipoCertificadoId = DB::table('tipos_certificado')->where('codigo', 'ACADEMICO')->value('id');

        DB::table('aulas')->insert(['iglesia_id' => $church, 'codigo' => 'C-AULA', 'nombre' => 'Aula cert', 'capacidad' => 20, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $aula = DB::table('aulas')->where('codigo', 'C-AULA')->value('id');

        DB::table('cursos')->insert(['iglesia_id' => $church, 'codigo' => 'C-CURSO', 'nombre' => 'Curso cert', 'descripcion' => null, 'activo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $curso = DB::table('cursos')->where('codigo', 'C-CURSO')->value('id');

        DB::table('programaciones_academicas')->insert([
            'curso_id' => $curso,
            'aula_id' => $aula,
            'periodo' => '2026-C',
            'grupo' => 'C',
            'fecha_inicio' => '2026-01-01',
            'fecha_fin' => '2026-06-01',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 11,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $programacionId = DB::table('programaciones_academicas')->where('periodo', '2026-C')->value('id');

        $matricula = Matricula::query()->create([
            'programacion_academica_id' => $programacionId,
            'miembro_id' => $member,
            'fecha_matricula' => now(),
            'estado' => 'activa',
        ]);

        Calificacion::query()->create([
            'matricula_id' => $matricula->id,
            'promedio_tareas' => 18,
            'nota_examen_final' => 18,
            'nota_final' => 18,
            'estado' => 'aprobada',
            'calculado_at' => now(),
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $sesion = Sesion::query()->create([
                'programacion_academica_id' => $programacionId,
                'orden' => $i,
                'inicio_at' => now()->subWeeks(6 - $i),
                'fin_at' => now()->subWeeks(6 - $i)->addHours(2),
                'tema' => "Sesión {$i}",
                'estado' => 'realizada',
            ]);

            if ($i <= 4) {
                Asistencia::query()->create([
                    'sesion_id' => $sesion->id,
                    'matricula_id' => $matricula->id,
                    'estado' => 'asistio',
                    'registrado_por_usuario_id' => $usuarioId,
                ]);
            }
        }

        return [
            'miembro_id' => $member,
            'programacion_id' => $programacionId,
            'tipo_certificado_id' => $tipoCertificadoId,
            'usuario_id' => $usuarioId,
        ];
    }
}
