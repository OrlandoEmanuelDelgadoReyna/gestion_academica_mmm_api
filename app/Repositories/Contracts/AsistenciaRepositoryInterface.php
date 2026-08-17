<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Sesion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AsistenciaRepositoryInterface
{
    public function paginate(int $perPage, ?int $sesionId = null): LengthAwarePaginator;

    public function create(array $data): Asistencia;

    public function update(Asistencia $asistencia, array $data): Asistencia;

    public function existsForSessionAndEnrollment(Sesion $sesion, Matricula $matricula, ?int $exceptId = null): bool;
}
