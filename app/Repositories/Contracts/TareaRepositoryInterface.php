<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Tarea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TareaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Tarea;

    public function update(Tarea $tarea, array $data): Tarea;

    public function delete(Tarea $tarea): void;
}
