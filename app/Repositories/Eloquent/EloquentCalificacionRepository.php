<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Calificacion;
use App\Repositories\Contracts\CalificacionRepositoryInterface;

final class EloquentCalificacionRepository implements CalificacionRepositoryInterface
{
    public function upsertForMatricula(int $matriculaId, array $data): Calificacion
    {
        return Calificacion::query()->updateOrCreate(['matricula_id' => $matriculaId], $data);
    }

    public function findByMatricula(int $matriculaId): ?Calificacion
    {
        return Calificacion::query()->where('matricula_id', $matriculaId)->first();
    }
}
