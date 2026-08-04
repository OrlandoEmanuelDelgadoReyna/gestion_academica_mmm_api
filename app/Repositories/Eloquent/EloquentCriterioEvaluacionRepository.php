<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\CriterioEvaluacion;
use App\Repositories\Contracts\CriterioEvaluacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentCriterioEvaluacionRepository implements CriterioEvaluacionRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return CriterioEvaluacion::query()->with('programacionAcademica')->orderBy('orden')->paginate($perPage);
    }

    public function create(array $data): CriterioEvaluacion
    {
        return CriterioEvaluacion::query()->create($data);
    }

    public function update(CriterioEvaluacion $criterio, array $data): CriterioEvaluacion
    {
        $criterio->update($data);

        return $criterio->refresh();
    }

    public function delete(CriterioEvaluacion $criterio): void
    {
        $criterio->delete();
    }

    public function forProgramacion(int $programacionId): Collection
    {
        return CriterioEvaluacion::query()->where('programacion_academica_id', $programacionId)->orderBy('orden')->get();
    }

    public function sumPorcentajes(int $programacionId, ?int $exceptId = null): float
    {
        $query = CriterioEvaluacion::query()->where('programacion_academica_id', $programacionId);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return (float) $query->sum('porcentaje');
    }
}
