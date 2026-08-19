<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Leccion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeccionRepositoryInterface
{
    public function paginate(int $perPage, int $cursoId): LengthAwarePaginator;

    public function create(array $data): Leccion;

    public function update(Leccion $leccion, array $data): Leccion;
}
