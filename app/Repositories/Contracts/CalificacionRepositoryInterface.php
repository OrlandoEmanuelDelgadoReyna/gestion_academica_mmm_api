<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Calificacion;

interface CalificacionRepositoryInterface
{
    public function upsertForMatricula(int $matriculaId, array $data): Calificacion;

    public function findByMatricula(int $matriculaId): ?Calificacion;
}
