<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Iglesia;
use App\Repositories\Contracts\IglesiaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentIglesiaRepository implements IglesiaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Iglesia::query()->withCount('miembros')->orderBy('nombre')->paginate($perPage);
    }

    public function create(array $attributes): Iglesia
    {
        return Iglesia::query()->create($attributes);
    }

    public function update(Iglesia $iglesia, array $attributes): Iglesia
    {
        $iglesia->update($attributes);

        return $iglesia->refresh();
    }
}
