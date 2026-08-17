<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SesionRepositoryInterface
{
    public function paginate(int $perPage, ?int $programacionAcademicaId = null): LengthAwarePaginator;

    public function create(array $data): Sesion;

    public function update(Sesion $sesion, array $data): Sesion;

    /** @return \Illuminate\Support\Collection<int, Sesion> */
    public function lockByProgramacion(ProgramacionAcademica $programacion): Collection;
}
