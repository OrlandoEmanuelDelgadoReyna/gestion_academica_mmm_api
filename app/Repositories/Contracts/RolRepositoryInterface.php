<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Rol;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RolRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Rol;

    public function update(Rol $rol, array $data): Rol;

    public function syncPermissions(Rol $rol, array $ids): void;
}
