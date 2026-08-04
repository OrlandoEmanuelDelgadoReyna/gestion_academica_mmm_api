<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Curso;
use App\Repositories\Contracts\CursoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCursoRepository implements CursoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Curso::query()->with('iglesia')->orderBy('nombre')->paginate($perPage);
    }

    public function create(array $data): Curso
    {
        return Curso::query()->create($data);
    }

    public function update(Curso $curso, array $data): Curso
    {
        $curso->update($data);

        return $curso->refresh();
    }
}
