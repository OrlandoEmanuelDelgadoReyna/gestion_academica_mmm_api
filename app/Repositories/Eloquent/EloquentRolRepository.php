<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Rol;
use App\Repositories\Contracts\RolRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentRolRepository implements RolRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Rol::query()->with('permisos')->paginate($perPage);
    }

    public function create(array $data): Rol
    {
        return Rol::query()->create($data);
    }

    public function update(Rol $rol, array $data): Rol
    {
        $rol->update($data);

        return $rol->refresh();
    }

    public function syncPermissions(Rol $rol, array $ids): void
    {
        $rol->permisos()->sync($ids);
    }
}
