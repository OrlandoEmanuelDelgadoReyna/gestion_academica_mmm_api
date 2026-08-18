<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ProgramacionAcademica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProgramacionAcademicaRepositoryInterface
{
    public function paginate(int $perPage, ?int $assignedMiembroId = null): LengthAwarePaginator;

    public function create(array $data): ProgramacionAcademica;

    public function update(ProgramacionAcademica $programacion, array $data): ProgramacionAcademica;

    public function updateEstado(ProgramacionAcademica $programacion, string $estado): ProgramacionAcademica;
}
