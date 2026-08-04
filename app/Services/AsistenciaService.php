<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Sesion;
use App\Repositories\Contracts\AsistenciaRepositoryInterface;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for attendance records. */
final class AsistenciaService
{
    public function __construct(
        private AsistenciaRepositoryInterface $asistencias,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->asistencias->paginate($perPage);
    }

    public function create(array $data, int $actor): Asistencia
    {
        $this->validateBusinessRules($data);

        return $this->transactions->execute(function () use ($data, $actor): Asistencia {
            $data['registrado_por_usuario_id'] = $actor;
            $asistencia = $this->asistencias->create($data);
            $this->auditorias->record($actor, 'CREATE', 'asistencias', $asistencia->id, null, $asistencia->getAttributes());

            return $asistencia->load(['sesion', 'matricula.miembro']);
        });
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
}
