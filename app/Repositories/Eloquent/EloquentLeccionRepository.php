<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Leccion;
use App\Repositories\Contracts\LeccionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentLeccionRepository implements LeccionRepositoryInterface
{
    public function paginate(int $perPage, int $cursoId): LengthAwarePaginator
    {
        return Leccion::query()
            ->where('curso_id', $cursoId)
            ->orderBy('orden')
            ->paginate($perPage);
    }

    public function create(array $data): Leccion
    {
        return Leccion::query()->create($data);
    }

    public function update(Leccion $leccion, array $data): Leccion
    {
        $leccion->update($data);

        return $leccion->refresh();
    }
}
