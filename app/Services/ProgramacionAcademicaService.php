<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\ProgramacionAcademicaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for academic program scheduling. */
final class ProgramacionAcademicaService
{
    public function __construct(
        private ProgramacionAcademicaRepositoryInterface $programaciones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->programaciones->paginate($perPage);
    }

    public function create(array $data, int $actor): ProgramacionAcademica
    {
        $data['estado'] ??= 'borrador';
        $this->validateBusinessRules($data);

        return $this->transactions->execute(function () use ($data, $actor): ProgramacionAcademica {
            $programacion = $this->programaciones->create($data);
            $this->auditorias->record($actor, 'CREATE', 'programaciones_academicas', $programacion->id, null, $programacion->getAttributes());

            return $programacion;
        });
    }

    public function update(ProgramacionAcademica $programacion, array $data, int $actor): ProgramacionAcademica
    {
        $this->validateBusinessRules(array_merge($programacion->getAttributes(), $data));

        return $this->transactions->execute(function () use ($programacion, $data, $actor): ProgramacionAcademica {
            $before = $programacion->getAttributes();
            $updated = $this->programaciones->update($programacion, $data);
            $this->auditorias->record($actor, 'UPDATE', 'programaciones_academicas', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function transitionEstado(ProgramacionAcademica $programacion, string $estado, int $actor): ProgramacionAcademica
    {
        return $this->transactions->execute(function () use ($programacion, $estado, $actor): ProgramacionAcademica {
            $before = $programacion->getAttributes();
            $updated = $this->programaciones->updateEstado($programacion, $estado);
            $this->auditorias->record($actor, 'STATE_TRANSITION', 'programaciones_academicas', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    private function validateBusinessRules(array $data): void
    {
        if (isset($data['fecha_inicio'], $data['fecha_fin']) && $data['fecha_fin'] < $data['fecha_inicio']) {
            throw ValidationException::withMessages(['fecha_fin' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.']);
        }

        if (isset($data['capacidad']) && (int) $data['capacidad'] <= 0) {
            throw ValidationException::withMessages(['capacidad' => 'La capacidad debe ser mayor que cero.']);
        }

        if (isset($data['nota_minima_aprobatoria'], $data['escala_maxima']) && (float) $data['nota_minima_aprobatoria'] > (float) $data['escala_maxima']) {
            throw ValidationException::withMessages(['nota_minima_aprobatoria' => 'La nota mínima no puede superar la escala máxima.']);
        }
    }
}
