<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\EntregaTarea;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;

final class EloquentEntregaTareaRepository implements EntregaTareaRepositoryInterface
{
    public function create(array $data): EntregaTarea
    {
        return EntregaTarea::query()->create($data);
    }

    public function update(EntregaTarea $entrega, array $data): EntregaTarea
    {
        $entrega->update($data);

        return $entrega->refresh();
    }
}
