<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Material;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MaterialRepositoryInterface
{
    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?int $assignedMiembroId = null): LengthAwarePaginator;

    public function create(array $data): Material;

    public function update(Material $material, array $data): Material;
}
