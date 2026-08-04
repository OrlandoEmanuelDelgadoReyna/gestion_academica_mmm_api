<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\IntentoExamen;
use App\Repositories\Contracts\IntentoExamenRepositoryInterface;

final class EloquentIntentoExamenRepository implements IntentoExamenRepositoryInterface
{
    public function create(array $data): IntentoExamen
    {
        return IntentoExamen::query()->create($data);
    }

    public function update(IntentoExamen $intento, array $data): IntentoExamen
    {
        $intento->update($data);

        return $intento->refresh();
    }

    public function countForMatricula(int $examenFinalId, int $matriculaId): int
    {
        return IntentoExamen::query()
            ->where('examen_final_id', $examenFinalId)
            ->where('matricula_id', $matriculaId)
            ->count();
    }
}
