<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MatriculaRepositoryInterface
{
    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?string $estado = null, ?int $assignedMiembroId = null): LengthAwarePaginator;

    public function create(array $data): Matricula;

    public function update(Matricula $matricula, array $data): Matricula;

    public function countEnrollments(ProgramacionAcademica $programacion): int;

    public function existsForMember(ProgramacionAcademica $programacion, Miembro $miembro): bool;

    public function hasScheduleConflict(Miembro $miembro, ProgramacionAcademica $programacion): bool;

    public function updateEstado(Matricula $matricula, string $estado): Matricula;
}
