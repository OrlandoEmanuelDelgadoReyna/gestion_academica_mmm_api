<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\BloqueCulto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BloqueCultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $cultoId = null): LengthAwarePaginator;

    public function create(array $data): BloqueCulto;

    public function update(BloqueCulto $bloqueCulto, array $data): BloqueCulto;

    public function delete(BloqueCulto $bloqueCulto): void;
}
