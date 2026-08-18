<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AsistenciaQrException;
use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Repositories\Contracts\AsistenciaRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/** Transactional application service for attendance records. */
final class AsistenciaService
{
    public function __construct(
        private AsistenciaRepositoryInterface $asistencias,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private SesionAsistenciaQrTokenService $qrTokens,
    ) {}

    public function paginate(int $perPage, ?int $sesionId = null, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        return $this->asistencias->paginate($perPage, $sesionId, $assignedMiembroId);
    }

    public function create(array $data, int $actor): Asistencia
    {
        $this->validateBusinessRules($data);

        try {
            return $this->transactions->execute(function () use ($data, $actor): Asistencia {
                $data['registrado_por_usuario_id'] = $actor;
                $asistencia = $this->asistencias->create($data);
                $this->auditorias->record($actor, 'CREATE', 'asistencias', $asistencia->id, null, $asistencia->getAttributes());

                return $asistencia->load(['sesion', 'matricula.miembro']);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'matricula_id' => 'Ya existe un registro de asistencia para esta sesión y matrícula.',
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintFailure($exception)) {
                throw ValidationException::withMessages([
                    'matricula_id' => 'Ya existe un registro de asistencia para esta sesión y matrícula.',
                ]);
            }

            throw $exception;
        }
    }

    public function registerFromQr(string $token, Usuario $user): Asistencia
    {
        $sesionId = $this->qrTokens->parse($token);
        $sesion = Sesion::query()->with(['programacionAcademica.curso'])->find($sesionId);

        if ($sesion === null) {
            throw AsistenciaQrException::unavailable();
        }

        if ($sesion->estado === 'cancelada') {
            throw AsistenciaQrException::cancelled();
        }

        $miembroId = $user->miembro_id;
        if ($miembroId === null) {
            throw AsistenciaQrException::withoutEnrollment();
        }

        $matricula = Matricula::query()
            ->where('miembro_id', $miembroId)
            ->where('programacion_academica_id', $sesion->programacion_academica_id)
            ->first();

        if ($matricula === null || $matricula->estado !== 'activa') {
            throw AsistenciaQrException::withoutEnrollment();
        }

        if ($this->asistencias->existsForSessionAndEnrollment($sesion, $matricula)) {
            throw AsistenciaQrException::alreadyRegistered();
        }

        try {
            return $this->create([
                'sesion_id' => $sesion->id,
                'matricula_id' => $matricula->id,
                'estado' => 'asistio',
            ], $user->id);
        } catch (ValidationException $exception) {
            if ($exception->errors()['matricula_id'] ?? null) {
                throw AsistenciaQrException::alreadyRegistered();
            }

            throw $exception;
        }
    }

    public function update(Asistencia $asistencia, array $data, int $actor): Asistencia
    {
        $merged = array_merge($asistencia->getAttributes(), $data);
        $this->validateBusinessRules($merged, $asistencia->id);

        return $this->transactions->execute(function () use ($asistencia, $data, $actor): Asistencia {
            $before = $asistencia->getAttributes();
            $updated = $this->asistencias->update($asistencia, $data);
            $this->auditorias->record($actor, 'UPDATE', 'asistencias', $updated->id, $before, $updated->getAttributes());

            return $updated->load(['sesion', 'matricula.miembro']);
        });
    }

    private function validateBusinessRules(array $data, ?int $exceptId = null): void
    {
        if (($data['estado'] ?? null) === 'justificado' && blank($data['observacion'] ?? null)) {
            throw ValidationException::withMessages(['observacion' => 'La observación es obligatoria cuando el estado es justificado.']);
        }

        if (! isset($data['sesion_id'], $data['matricula_id'])) {
            return;
        }

        $sesion = Sesion::query()->find($data['sesion_id']);
        $matricula = Matricula::query()->find($data['matricula_id']);

        if ($sesion === null || $matricula === null) {
            return;
        }

        if ($matricula->programacion_academica_id !== $sesion->programacion_academica_id) {
            throw ValidationException::withMessages(['matricula_id' => 'La matrícula no pertenece a la misma programación que la sesión.']);
        }

        if ($this->asistencias->existsForSessionAndEnrollment($sesion, $matricula, $exceptId)) {
            throw ValidationException::withMessages(['matricula_id' => 'Ya existe un registro de asistencia para esta sesión y matrícula.']);
        }
    }

    private function isUniqueConstraintFailure(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();

        return $sqlState === '23000'
            || str_contains($message, 'UNIQUE constraint failed')
            || str_contains($message, 'Duplicate entry');
    }
}
