<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CriterioEvaluacion;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CriterioEvaluacionRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Manages weighted evaluation criteria with percentage validation. */
final class CriterioEvaluacionService
{
    public function __construct(
        private CriterioEvaluacionRepositoryInterface $criterios,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->criterios->paginate($perPage);
    }

    public function create(array $data, int $actor): CriterioEvaluacion
    {
        return $this->transactions->execute(function () use ($data, $actor): CriterioEvaluacion {
            $criterio = $this->criterios->create($data);
            $this->assertPorcentajesSum100((int) $criterio->programacion_academica_id);
            $this->auditorias->record($actor, 'CREATE', 'criterios_evaluacion', $criterio->id, null, $criterio->getAttributes());

            return $criterio->refresh();
        });
    }

    public function update(CriterioEvaluacion $criterio, array $data, int $actor): CriterioEvaluacion
    {
        return $this->transactions->execute(function () use ($criterio, $data, $actor): CriterioEvaluacion {
            $before = $criterio->getAttributes();
            $updated = $this->criterios->update($criterio, $data);
            $this->assertPorcentajesSum100((int) $updated->programacion_academica_id);
            $this->auditorias->record($actor, 'UPDATE', 'criterios_evaluacion', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(CriterioEvaluacion $criterio, int $actor): void
    {
        $this->transactions->execute(function () use ($criterio, $actor): void {
            $before = $criterio->getAttributes();
            $this->criterios->delete($criterio);
            $this->auditorias->record($actor, 'DELETE', 'criterios_evaluacion', $criterio->id, $before, null);
        });
    }

    private function assertPorcentajesSum100(int $programacionId): void
    {
        $criterios = $this->criterios->forProgramacion($programacionId);
        $sum = (float) $criterios->sum('porcentaje');

        if ($sum > 100.01) {
            throw ValidationException::withMessages(['porcentaje' => 'La suma de porcentajes no puede exceder 100.']);
        }

        if ($criterios->count() >= 2 && abs($sum - 100) > 0.01) {
            throw ValidationException::withMessages(['porcentaje' => 'Los porcentajes de los criterios deben sumar 100.']);
        }
    }
}
