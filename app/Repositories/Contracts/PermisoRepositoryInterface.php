<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Permiso;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PermisoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Permiso;

    public function update(Permiso $permiso, array $data): Permiso;
}
