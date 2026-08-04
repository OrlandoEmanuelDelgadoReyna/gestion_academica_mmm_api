<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Iglesia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface IglesiaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Iglesia;

    public function update(Iglesia $iglesia, array $attributes): Iglesia;
}
