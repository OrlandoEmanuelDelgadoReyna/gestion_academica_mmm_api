<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\EntregaTarea;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EntregaTareaRepositoryInterface
{
    public function paginate(
        int $perPage,
        ?int $tareaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator;

    public function create(array $data): EntregaTarea;

    public function update(EntregaTarea $entrega, array $data): EntregaTarea;
}
