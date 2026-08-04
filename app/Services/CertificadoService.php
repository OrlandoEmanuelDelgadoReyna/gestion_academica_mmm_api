<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\Sesion;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CertificadoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Manages certificate issuance, revocation, replacement and verification. */
final class CertificadoService
{
    private const float ASISTENCIA_MINIMA = 80.0;

    public function __construct(
        private CertificadoRepositoryInterface $certificados,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->certificados->paginate($perPage);
    }

    public function emitir(array $data, int $actor): Certificado
    {
        return $this->transactions->execute(function () use ($data, $actor): Certificado {
            $matricula = Matricula::query()
                ->with('miembro')
                ->where('programacion_academica_id', $data['programacion_academica_id'])
                ->where('miembro_id', $data['miembro_id'])
                ->first();

            if ($matricula === null) {
                throw ValidationException::withMessages(['matricula' => 'No existe matrícula para el miembro en esta programación.']);
            }

            $calificacion = Calificacion::query()->where('matricula_id', $matricula->id)->first();

            if ($calificacion === null || $calificacion->estado !== 'aprobada') {
                throw ValidationException::withMessages(['calificacion' => 'El miembro debe tener calificación aprobada.']);
            }

            $this->assertAsistenciaMinima($matricula);

            $payload = array_merge($data, [
                'codigo_verificacion' => (string) Str::uuid(),
                'emitido_at' => now(),
                'estado' => 'emitido',
                'emitido_por_usuario_id' => $actor,
            ]);

            $certificado = $this->certificados->create($payload);
            $this->auditorias->record($actor, 'CREATE', 'certificados', $certificado->id, null, $certificado->getAttributes());

            return $certificado;
        });
    }

    public function revocar(Certificado $certificado, string $motivo, int $actor): Certificado
    {
        return $this->transactions->execute(function () use ($certificado, $motivo, $actor): Certificado {
            if ($certificado->estado !== 'emitido') {
                throw ValidationException::withMessages(['certificado' => 'Solo se pueden revocar certificados emitidos.']);
            }

            $before = $certificado->getAttributes();
            $updated = $this->certificados->update($certificado, [
                'estado' => 'revocado',
                'motivo' => $motivo,
            ]);

            $this->auditorias->record($actor, 'UPDATE', 'certificados', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function reemplazar(Certificado $certificado, array $data, int $actor): Certificado
    {
        return $this->transactions->execute(function () use ($certificado, $data, $actor): Certificado {
            if (! in_array($certificado->estado, ['emitido', 'revocado'], true)) {
                throw ValidationException::withMessages(['certificado' => 'El certificado no puede reemplazarse en su estado actual.']);
            }

            $before = $certificado->getAttributes();
            $this->certificados->update($certificado, ['estado' => 'reemplazado']);

            $nuevo = $this->certificados->create([
                'miembro_id' => $certificado->miembro_id,
                'tipo_certificado_id' => $data['tipo_certificado_id'] ?? $certificado->tipo_certificado_id,
                'programacion_academica_id' => $certificado->programacion_academica_id,
                'certificado_reemplazado_id' => $certificado->id,
                'codigo_verificacion' => (string) Str::uuid(),
                'emitido_at' => now(),
                'estado' => 'emitido',
                'destinatario' => $data['destinatario'] ?? $certificado->destinatario,
                'vence_at' => $data['vence_at'] ?? $certificado->vence_at,
                'ruta_documento' => $data['ruta_documento'] ?? $certificado->ruta_documento,
                'emitido_por_usuario_id' => $actor,
            ]);

            $this->auditorias->record($actor, 'UPDATE', 'certificados', $certificado->id, $before, $certificado->refresh()->getAttributes());
            $this->auditorias->record($actor, 'CREATE', 'certificados', $nuevo->id, null, $nuevo->getAttributes());

            return $nuevo;
        });
    }

    public function verificar(string $codigo): ?Certificado
    {
        $certificado = $this->certificados->findByCodigo($codigo);

        if ($certificado === null || $certificado->estado !== 'emitido') {
            return null;
        }

        if ($certificado->vence_at !== null && now()->gt($certificado->vence_at)) {
            return null;
        }

        return $certificado;
    }

    private function assertAsistenciaMinima(Matricula $matricula): void
    {
        $totalSesiones = Sesion::query()
            ->where('programacion_academica_id', $matricula->programacion_academica_id)
            ->count();

        if ($totalSesiones === 0) {
            throw ValidationException::withMessages(['asistencia' => 'No hay sesiones registradas para calcular asistencia.']);
        }

        $asistenciasPresentes = Asistencia::query()
            ->where('matricula_id', $matricula->id)
            ->whereIn('estado', ['asistio', 'justificado'])
            ->count();

        $porcentaje = ($asistenciasPresentes / $totalSesiones) * 100;

        if ($porcentaje < self::ASISTENCIA_MINIMA) {
            throw ValidationException::withMessages(['asistencia' => 'La asistencia mínima requerida es del 80%.']);
        }
    }
}
