<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Sesion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface SesionRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Sesion;

    public function update(Sesion $sesion, array $data): Sesion;
}
