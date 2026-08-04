<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ExamenFinal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExamenFinalRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): ExamenFinal;

    public function update(ExamenFinal $examen, array $data): ExamenFinal;

    public function delete(ExamenFinal $examen): void;
}
