<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Permiso;
use App\Repositories\Contracts\PermisoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentPermisoRepository implements PermisoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Permiso::query()->orderBy('modulo')->orderBy('codigo')->paginate($perPage);
    }

    public function create(array $data): Permiso
    {
        return Permiso::query()->create($data);
    }

    public function update(Permiso $permiso, array $data): Permiso
    {
        $permiso->update($data);

        return $permiso->refresh();
    }
}
