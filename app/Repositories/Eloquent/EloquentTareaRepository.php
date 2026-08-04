<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Tarea;
use App\Repositories\Contracts\TareaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentTareaRepository implements TareaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Tarea::query()->with(['programacionAcademica', 'creadoPor'])->orderByDesc('publicado_at')->paginate($perPage);
    }

    public function create(array $data): Tarea
    {
        return Tarea::query()->create($data);
    }

    public function update(Tarea $tarea, array $data): Tarea
    {
        $tarea->update($data);

        return $tarea->refresh();
    }

    public function delete(Tarea $tarea): void
    {
        $tarea->delete();
    }
}
