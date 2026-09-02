<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\Sesion;
use App\Models\TipoCertificado;
use App\Models\Usuario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CertificadoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Support\MaterialStorage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Manages certificate issuance, revocation, replacement and verification. */
final class CertificadoService
{
    public const float ASISTENCIA_MINIMA = 80.0;

    public function __construct(
        private CertificadoRepositoryInterface $certificados,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private CalificacionService $calificaciones,
        private CertificadoPdfGenerator $pdfs,
        private CertificadoNotificacionDispatcher $notificaciones,
        private AcademicAccess $academicAccess,
    ) {}

    public function paginate(Usuario $user, int $perPage, ?int $programacionAcademicaId = null, ?int $miembroId = null): LengthAwarePaginator
    {
        return $this->certificados->paginateFor($user, $perPage, $programacionAcademicaId, $miembroId);
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluarElegibilidad(int $miembroId, int $programacionAcademicaId, int $actorId, ?int $tipoCertificadoId = null, bool $recalcular = true): array
    {
        $tipo = $this->resolveTipo($tipoCertificadoId);

        if ($tipo->codigo !== TipoCertificado::CODIGO_ACADEMICO) {
            return [
                'elegible' => true,
                'tipo_codigo' => $tipo->codigo,
                'aplica_requisitos_academicos' => false,
                'matricula_encontrada' => null,
                'matricula_estado' => null,
                'curso_aprobado' => null,
                'nota_final' => null,
                'calificacion_estado' => null,
                'asistencia_porcentaje' => null,
                'asistencia_cumple' => null,
                'certificado_vigente_id' => null,
                'motivos' => ['La carta de recomendación no usa requisitos académicos.'],
            ];
        }

        return $this->evaluarAcademico($miembroId, $programacionAcademicaId, $actorId, $tipo, $recalcular);
    }

    public function emitir(array $data, int $actor): Certificado
    {
        $this->assertChurchScope($actor, (int) $data['miembro_id']);
        unset($data['ruta_documento']);

        $tipo = TipoCertificado::query()->findOrFail((int) $data['tipo_certificado_id']);
        $ruta = null;

        try {
            $certificado = $this->transactions->execute(function () use ($data, $actor, $tipo, &$ruta): Certificado {
                if ($tipo->codigo === TipoCertificado::CODIGO_ACADEMICO) {
                    $this->assertPuedeEmitirAcademico($data, $actor);
                }

                $payload = array_merge($data, [
                    'codigo_verificacion' => (string) Str::uuid(),
                    'emitido_at' => now(),
                    'estado' => Certificado::ESTADO_EMITIDO,
                    'emitido_por_usuario_id' => $actor,
                    'ruta_documento' => null,
                ]);
                unset($payload['ruta_documento_cliente']);

                $certificado = $this->certificados->create($payload);
                $certificado = $this->attachDocumento($certificado);
                $ruta = $certificado->ruta_documento;
                $this->auditorias->record($actor, 'CREATE', 'certificados', $certificado->id, null, $this->auditAttributes($certificado));

                return $certificado;
            });
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($ruta);
            throw $exception;
        }

        $this->notifyAlumno($certificado, $actor);

        return $certificado->load(['miembro', 'tipoCertificado', 'programacionAcademica.curso']);
    }

    public function revocar(Certificado $certificado, string $motivo, int $actor): Certificado
    {
        return $this->transactions->execute(function () use ($certificado, $motivo, $actor): Certificado {
            if ($certificado->estado !== Certificado::ESTADO_EMITIDO) {
                throw ValidationException::withMessages(['certificado' => 'Solo se pueden revocar certificados emitidos.']);
            }

            $before = $this->auditAttributes($certificado);
            $updated = $this->certificados->update($certificado, [
                'estado' => Certificado::ESTADO_REVOCADO,
                'motivo' => $motivo,
            ]);

            $this->auditorias->record($actor, 'UPDATE', 'certificados', $updated->id, $before, $this->auditAttributes($updated));

            return $updated;
        });
    }

    public function reemplazar(Certificado $certificado, array $data, int $actor): Certificado
    {
        $ruta = null;

        try {
            $nuevo = $this->transactions->execute(function () use ($certificado, $data, $actor, &$ruta): Certificado {
                if (! in_array($certificado->estado, [Certificado::ESTADO_EMITIDO, Certificado::ESTADO_REVOCADO], true)) {
                    throw ValidationException::withMessages(['certificado' => 'El certificado no puede reemplazarse en su estado actual.']);
                }

                $tipoId = (int) ($data['tipo_certificado_id'] ?? $certificado->tipo_certificado_id);
                $this->assertNoOtroEmitidoVigente(
                    (int) $certificado->miembro_id,
                    $certificado->programacion_academica_id !== null ? (int) $certificado->programacion_academica_id : null,
                    $tipoId,
                    (int) $certificado->id,
                );

                $before = $this->auditAttributes($certificado);
                $this->certificados->update($certificado, ['estado' => Certificado::ESTADO_REEMPLAZADO]);

                $nuevo = $this->certificados->create([
                    'miembro_id' => $certificado->miembro_id,
                    'tipo_certificado_id' => $data['tipo_certificado_id'] ?? $certificado->tipo_certificado_id,
                    'programacion_academica_id' => $certificado->programacion_academica_id,
                    'certificado_reemplazado_id' => $certificado->id,
                    'codigo_verificacion' => (string) Str::uuid(),
                    'emitido_at' => now(),
                    'estado' => Certificado::ESTADO_EMITIDO,
                    'destinatario' => $data['destinatario'] ?? $certificado->destinatario,
                    'vence_at' => $data['vence_at'] ?? $certificado->vence_at,
                    'ruta_documento' => null,
                    'emitido_por_usuario_id' => $actor,
                ]);

                $nuevo = $this->attachDocumento($nuevo);
                $ruta = $nuevo->ruta_documento;

                $this->auditorias->record($actor, 'UPDATE', 'certificados', $certificado->id, $before, $this->auditAttributes($certificado->refresh()));
                $this->auditorias->record($actor, 'CREATE', 'certificados', $nuevo->id, null, $this->auditAttributes($nuevo));

                return $nuevo;
            });
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($ruta);
            throw $exception;
        }

        $this->notifyAlumno($nuevo, $actor);

        return $nuevo->load(['miembro', 'tipoCertificado', 'programacionAcademica.curso']);
    }

    public function verificar(string $codigo): ?Certificado
    {
        return $this->certificados->findByCodigo($codigo);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertPuedeEmitirAcademico(array $data, int $actor): void
    {
        if (empty($data['programacion_academica_id'])) {
            throw ValidationException::withMessages([
                'programacion_academica_id' => 'La programación académica es requerida para un certificado académico.',
            ]);
        }

        $evaluacion = $this->evaluarAcademico(
            (int) $data['miembro_id'],
            (int) $data['programacion_academica_id'],
            $actor,
            TipoCertificado::query()->findOrFail((int) $data['tipo_certificado_id']),
            true,
            true,
        );

        if (! $evaluacion['elegible']) {
            throw ValidationException::withMessages([
                'certificado' => implode(' ', $evaluacion['motivos']),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluarAcademico(
        int $miembroId,
        int $programacionAcademicaId,
        int $actorId,
        TipoCertificado $tipo,
        bool $recalcular,
        bool $bloquearFilas = false,
    ): array {
        $motivos = [];
        $calificacion = null;
        $calculoOk = false;
        $query = Matricula::query()
            ->where('programacion_academica_id', $programacionAcademicaId)
            ->where('miembro_id', $miembroId);

        if ($bloquearFilas) {
            $query->lockForUpdate();
        }

        $matricula = $query->first();
        $asistencia = [
            'porcentaje' => null,
            'cumple' => false,
            'total_sesiones' => 0,
            'presentes' => 0,
        ];
        $cursoAprobado = false;
        $notaFinal = null;
        $calificacionEstado = null;
        $vigenteId = null;

        if ($matricula === null) {
            $motivos[] = 'No existe matrícula para el miembro en esta programación.';
        } else {
            $motivos[] = 'Matrícula encontrada.';

            if ($recalcular) {
                try {
                    $calificacion = $this->calificaciones->calcular($matricula, $actorId);
                    $calculoOk = true;
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $messages) {
                        foreach ($messages as $message) {
                            $motivos[] = $message;
                        }
                    }
                }
            } else {
                $calificacion = Calificacion::query()->where('matricula_id', $matricula->id)->first();
                $calculoOk = $calificacion !== null;
            }

            if ($calificacion === null) {
                if (! $this->containsMotivo($motivos, 'No hay criterios')
                    && ! $this->containsMotivo($motivos, 'Falta calificación')
                    && ! $this->containsMotivo($motivos, 'deben sumar 100')
                ) {
                    $motivos[] = 'No existe calificación del curso.';
                }
            } elseif ($calculoOk) {
                $notaFinal = $calificacion->nota_final !== null ? (float) $calificacion->nota_final : null;
                $calificacionEstado = $calificacion->estado;
                if ($calificacion->estado === 'aprobada') {
                    $cursoAprobado = true;
                    $motivos[] = 'Curso aprobado.';
                } else {
                    $motivos[] = 'Curso desaprobado.';
                }
            }

            $asistencia = $this->asistenciaDe($matricula);
            if ($asistencia['total_sesiones'] === 0) {
                $motivos[] = 'No hay sesiones registradas para calcular asistencia.';
            } elseif ($asistencia['cumple']) {
                $motivos[] = 'Asistencia: '.$this->formatPorcentaje($asistencia['porcentaje']).'.';
            } else {
                $motivos[] = 'Asistencia: '.$this->formatPorcentaje($asistencia['porcentaje']).'. La asistencia mínima requerida es del 80%.';
            }

            $vigente = Certificado::query()
                ->where('miembro_id', $matricula->miembro_id)
                ->where('programacion_academica_id', $matricula->programacion_academica_id)
                ->where('tipo_certificado_id', $tipo->id)
                ->where('estado', Certificado::ESTADO_EMITIDO)
                ->when($bloquearFilas, fn ($q) => $q->lockForUpdate())
                ->first();

            if ($vigente !== null) {
                $vigenteId = $vigente->id;
                $motivos[] = 'Ya existe un certificado emitido vigente para esta programación.';
            }
        }

        $elegible = $matricula !== null
            && $cursoAprobado
            && $asistencia['cumple']
            && $vigenteId === null
            && ! $this->containsBlockingCalculo($motivos);

        if ($elegible) {
            $motivos[] = 'Elegible para certificado.';
        } elseif (! $this->containsMotivo($motivos, 'No elegible') && ! $this->containsMotivo($motivos, 'Elegible para certificado')) {
            $motivos[] = 'No elegible.';
        }

        return [
            'elegible' => $elegible,
            'tipo_codigo' => $tipo->codigo,
            'aplica_requisitos_academicos' => true,
            'matricula_encontrada' => $matricula !== null,
            'matricula_estado' => $matricula?->estado,
            'curso_aprobado' => $matricula === null ? null : $cursoAprobado,
            'nota_final' => $notaFinal,
            'calificacion_estado' => $calificacionEstado,
            'asistencia_porcentaje' => $asistencia['porcentaje'],
            'asistencia_cumple' => $asistencia['total_sesiones'] === 0 ? false : $asistencia['cumple'],
            'certificado_vigente_id' => $vigenteId,
            'motivos' => array_values(array_unique($motivos)),
        ];
    }

    /** @return array{porcentaje: ?float, cumple: bool, total_sesiones: int, presentes: int} */
    private function asistenciaDe(Matricula $matricula): array
    {
        $totalSesiones = Sesion::query()
            ->where('programacion_academica_id', $matricula->programacion_academica_id)
            ->count();

        if ($totalSesiones === 0) {
            return [
                'porcentaje' => null,
                'cumple' => false,
                'total_sesiones' => 0,
                'presentes' => 0,
            ];
        }

        $presentes = Asistencia::query()
            ->where('matricula_id', $matricula->id)
            ->whereIn('estado', ['asistio', 'justificado'])
            ->count();

        $porcentaje = round(($presentes / $totalSesiones) * 100, 2);

        return [
            'porcentaje' => $porcentaje,
            'cumple' => $porcentaje >= self::ASISTENCIA_MINIMA,
            'total_sesiones' => $totalSesiones,
            'presentes' => $presentes,
        ];
    }

    private function attachDocumento(Certificado $certificado): Certificado
    {
        $path = $this->pdfs->store($certificado->fresh([
            'miembro.iglesia',
            'tipoCertificado',
            'programacionAcademica.curso.iglesia',
        ]) ?? $certificado);

        return $this->certificados->update($certificado, ['ruta_documento' => $path]);
    }

    private function assertChurchScope(int $actorId, int $miembroId): void
    {
        $actor = Usuario::query()->findOrFail($actorId);
        if (! $this->academicAccess->canIssueCertificatesForMember($actor, $miembroId)) {
            throw new AuthorizationException('No puede emitir certificados fuera de su iglesia.');
        }
    }

    private function assertNoOtroEmitidoVigente(int $miembroId, ?int $programacionAcademicaId, int $tipoCertificadoId, int $exceptoId): void
    {
        if ($programacionAcademicaId === null) {
            return;
        }

        $vigente = Certificado::query()
            ->where('miembro_id', $miembroId)
            ->where('programacion_academica_id', $programacionAcademicaId)
            ->where('tipo_certificado_id', $tipoCertificadoId)
            ->where('estado', Certificado::ESTADO_EMITIDO)
            ->where('id', '!=', $exceptoId)
            ->lockForUpdate()
            ->exists();

        if ($vigente) {
            throw ValidationException::withMessages([
                'certificado' => 'Ya existe un certificado emitido vigente para esta programación.',
            ]);
        }
    }

    private function resolveTipo(?int $tipoCertificadoId): TipoCertificado
    {
        if ($tipoCertificadoId !== null) {
            return TipoCertificado::query()->findOrFail($tipoCertificadoId);
        }

        return TipoCertificado::query()->where('codigo', TipoCertificado::CODIGO_ACADEMICO)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function auditAttributes(Certificado $certificado): array
    {
        $attributes = $certificado->getAttributes();
        unset($attributes['ruta_documento']);

        return $attributes;
    }

    /** @param  list<string>  $motivos */
    private function containsMotivo(array $motivos, string $needle): bool
    {
        foreach ($motivos as $motivo) {
            if (stripos($motivo, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $motivos */
    private function containsBlockingCalculo(array $motivos): bool
    {
        foreach ($motivos as $motivo) {
            if (stripos($motivo, 'No hay criterios') !== false
                || stripos($motivo, 'deben sumar 100') !== false
                || stripos($motivo, 'Falta calificación') !== false
                || stripos($motivo, 'Origen de criterio') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function notifyAlumno(Certificado $certificado, int $actor): void
    {
        try {
            $this->notificaciones->certificadoDisponible($certificado, $actor);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function formatPorcentaje(?float $porcentaje): string
    {
        if ($porcentaje === null) {
            return '0%';
        }

        return rtrim(rtrim(number_format($porcentaje, 2, '.', ''), '0'), '.').'%';
    }
}
