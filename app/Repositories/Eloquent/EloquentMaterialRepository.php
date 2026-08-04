<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentMaterialRepository implements MaterialRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Material::query()
            ->with(['programacionAcademica.curso', 'tipoMaterial'])
            ->orderByDesc('publicado_at')
            ->paginate($perPage);
    }

    public function create(array $data): Material
    {
        return Material::query()->create($data);
    }

    public function update(Material $material, array $data): Material
    {
        $material->update($data);

        return $material->refresh();
    }
}
