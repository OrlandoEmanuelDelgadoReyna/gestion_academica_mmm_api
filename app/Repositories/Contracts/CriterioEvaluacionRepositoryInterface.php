<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\CriterioEvaluacion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CriterioEvaluacionRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): CriterioEvaluacion;

    public function update(CriterioEvaluacion $criterio, array $data): CriterioEvaluacion;

    public function delete(CriterioEvaluacion $criterio): void;

    public function forProgramacion(int $programacionId): Collection;

    public function sumPorcentajes(int $programacionId, ?int $exceptId = null): float;
}
