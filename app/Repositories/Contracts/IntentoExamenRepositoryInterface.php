<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\IntentoExamen;

interface IntentoExamenRepositoryInterface
{
    public function create(array $data): IntentoExamen;

    public function update(IntentoExamen $intento, array $data): IntentoExamen;

    public function countForMatricula(int $examenFinalId, int $matriculaId): int;
}
