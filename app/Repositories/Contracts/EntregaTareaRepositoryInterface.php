<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\EntregaTarea;

interface EntregaTareaRepositoryInterface
{
    public function create(array $data): EntregaTarea;

    public function update(EntregaTarea $entrega, array $data): EntregaTarea;
}
