<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\AsistenciaQrException;
use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\SesionAsistenciaQrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AuthenticatesApiUsers;
use Tests\TestCase;

final class AsistenciaQrApiTest extends TestCase
{
    use AuthenticatesApiUsers;
    use RefreshDatabase;

    private SesionAsistenciaQrTokenService $tokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedInstitutionalCatalog();
        $this->tokens = $this->app->make(SesionAsistenciaQrTokenService::class);
    }

    public function test_valid_qr_registers_attendance(): void
    {
        $context = $this->createQrContext('QR-1');
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
        ])->assertCreated()
            ->assertJsonPath('code', AsistenciaQrException::ASISTENCIA_REGISTRADA)
            ->assertJsonPath('message', 'Asistencia registrada correctamente.')
            ->assertJsonPath('data.estado', 'asistio')
            ->assertJsonPath('data.sesion_id', $context['sesion']->id)
            ->assertJsonPath('data.matricula_id', $context['matricula']->id);

        $this->assertDatabaseHas('asistencias', [
            'sesion_id' => $context['sesion']->id,
            'matricula_id' => $context['matricula']->id,
            'estado' => 'asistio',
            'registrado_por_usuario_id' => $context['alumno']->id,
        ]);
    }

    public function test_invalid_qr_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-2');
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => 'token-invalido',
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::QR_INVALIDO)
            ->assertJsonPath('message', 'El código QR no es válido.');
    }

    public function test_tampered_qr_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-3');
        Sanctum::actingAs($context['alumno']);
        $token = $this->tokens->issue($context['sesion']);
        $tampered = substr($token, 0, -4).'XXXX';

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $tampered,
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::QR_INVALIDO);
    }

    public function test_user_without_enrollment_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-4');
        $otro = $this->createAlumnoUser('QR-4B', '44440001');
        Sanctum::actingAs($otro['usuario']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::SIN_MATRICULA)
            ->assertJsonPath('message', 'No tienes una matrícula activa para esta sesión.');
    }

    public function test_retirada_enrollment_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-5');
        $context['matricula']->update(['estado' => 'retirada']);
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::SIN_MATRICULA);
    }

    public function test_completada_enrollment_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-6');
        $context['matricula']->update(['estado' => 'completada']);
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::SIN_MATRICULA);
    }

    public function test_enrollment_from_other_programacion_is_unprocessable(): void
    {
        $destino = $this->createQrContext('QR-7A');
        $otra = $this->createQrContext('QR-7B');
        Sanctum::actingAs($otra['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($destino['sesion']),
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::SIN_MATRICULA);
    }

    public function test_cancelled_session_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-8');
        $context['sesion']->update(['estado' => 'cancelada']);
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
        ])->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::SESION_CANCELADA)
            ->assertJsonPath('message', 'Esta sesión está cancelada y no permite registrar asistencia.');
    }

    public function test_duplicate_qr_checkin_is_unprocessable(): void
    {
        $context = $this->createQrContext('QR-9');
        Sanctum::actingAs($context['alumno']);
        $token = $this->tokens->issue($context['sesion']);

        $this->postJson('/api/v1/asistencias/qr', ['token' => $token])->assertCreated();

        $this->postJson('/api/v1/asistencias/qr', ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonPath('code', AsistenciaQrException::ASISTENCIA_YA_REGISTRADA)
            ->assertJsonPath('message', 'Tu asistencia para esta sesión ya fue registrada.');

        $this->assertSame(1, Asistencia::query()->where('sesion_id', $context['sesion']->id)->count());
    }

    public function test_unauthenticated_qr_checkin_is_unauthorized(): void
    {
        $context = $this->createQrContext('QR-10');
        $token = $this->tokens->issue($context['sesion']);

        $this->postJson('/api/v1/asistencias/qr', ['token' => $token])
            ->assertUnauthorized();
    }

    public function test_qr_always_registers_asistio(): void
    {
        $context = $this->createQrContext('QR-11');
        Sanctum::actingAs($context['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($context['sesion']),
            'estado' => 'falto',
            'matricula_id' => 999999,
            'sesion_id' => 999999,
        ])->assertCreated()
            ->assertJsonPath('data.estado', 'asistio')
            ->assertJsonPath('data.matricula_id', $context['matricula']->id)
            ->assertJsonPath('data.sesion_id', $context['sesion']->id);
    }

    public function test_user_a_cannot_register_attendance_for_user_b(): void
    {
        $contextA = $this->createQrContext('QR-12A');
        $contextB = $this->createQrContext('QR-12B');
        Sanctum::actingAs($contextA['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($contextA['sesion']),
            'matricula_id' => $contextB['matricula']->id,
        ])->assertCreated()
            ->assertJsonPath('data.matricula_id', $contextA['matricula']->id);

        $this->assertDatabaseMissing('asistencias', [
            'matricula_id' => $contextB['matricula']->id,
        ]);
    }

    public function test_qr_from_session_a_does_not_register_session_b(): void
    {
        $first = $this->createQrContext('QR-13A');
        $second = $this->createQrContext('QR-13B', $first['alumno']->miembro_id, $first['alumno']);
        Sanctum::actingAs($first['alumno']);

        $this->postJson('/api/v1/asistencias/qr', [
            'token' => $this->tokens->issue($first['sesion']),
        ])->assertCreated()
            ->assertJsonPath('data.sesion_id', $first['sesion']->id);

        $this->assertDatabaseMissing('asistencias', [
            'sesion_id' => $second['sesion']->id,
        ]);
    }

    /**
     * @return array{sesion: Sesion, matricula: Matricula, alumno: Usuario}
     */
    private function createQrContext(string $codigo, ?int $miembroId = null, ?Usuario $usuario = null): array
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $curso = Curso::query()->create([
            'iglesia_id' => $church,
            'codigo' => $codigo,
            'nombre' => "Curso {$codigo}",
            'activo' => true,
        ]);

        $programacion = ProgramacionAcademica::query()->create([
            'curso_id' => $curso->id,
            'periodo' => '2026-II',
            'grupo' => substr($codigo, -2),
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-30',
            'capacidad' => 20,
            'escala_maxima' => 20,
            'nota_minima_aprobatoria' => 14,
            'maximo_intentos_examen' => 1,
            'estado' => 'abierta',
        ]);

        $sesion = Sesion::query()->create([
            'programacion_academica_id' => $programacion->id,
            'orden' => 3,
            'inicio_at' => '2026-08-20 19:00:00',
            'fin_at' => '2026-08-20 21:00:00',
            'tema' => null,
            'estado' => 'programada',
        ]);

        if ($usuario === null) {
            $alumno = $this->createAlumnoUser($codigo, sprintf('%08d', abs(crc32($codigo)) % 90000000 + 10000000));
            $usuario = $alumno['usuario'];
            $miembroId = $alumno['miembro']->id;
        }

        $matricula = Matricula::query()->create([
            'programacion_academica_id' => $programacion->id,
            'miembro_id' => $miembroId,
            'fecha_matricula' => now(),
            'estado' => 'activa',
        ]);

        return [
            'sesion' => $sesion,
            'matricula' => $matricula,
            'alumno' => $usuario,
        ];
    }

    /** @return array{usuario: Usuario, miembro: Miembro} */
    private function createAlumnoUser(string $codigo, string $documento): array
    {
        $church = (int) DB::table('iglesias')->where('codigo', 'MMM-PRINCIPAL')->value('id');
        $miembro = Miembro::query()->create([
            'iglesia_id' => $church,
            'tipo_documento' => 'DNI',
            'numero_documento' => $documento,
            'nombres' => 'Alumno',
            'apellidos' => $codigo,
            'fecha_nacimiento' => '2000-01-01',
            'sexo' => 'M',
            'correo_electronico' => strtolower($codigo).'@mmm.local',
            'telefono' => '955555555',
            'direccion' => 'Dirección alumno',
        ]);

        $usuario = Usuario::query()->create([
            'miembro_id' => $miembro->id,
            'nombre_usuario' => 'alumno.'.strtolower($codigo),
            'contrasena' => 'Admin123*',
            'activo' => true,
        ]);

        return ['usuario' => $usuario, 'miembro' => $miembro];
    }
}
