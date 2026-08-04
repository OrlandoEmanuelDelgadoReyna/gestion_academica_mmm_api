<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Culto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    public function create(array $data): Culto;

    public function update(Culto $culto, array $data): Culto;

    public function delete(Culto $culto): void;
}
