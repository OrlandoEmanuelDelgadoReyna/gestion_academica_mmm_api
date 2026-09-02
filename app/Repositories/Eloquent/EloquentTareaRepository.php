<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Tarea;
use App\Repositories\Contracts\TareaRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentTareaRepository implements TareaRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginate(
        int $perPage,
        ?int $programacionAcademicaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator {
        $query = Tarea::query()
            ->with(['programacionAcademica', 'creadoPor'])
            ->when(
                $programacionAcademicaId !== null,
                fn ($builder) => $builder->where('programacion_academica_id', $programacionAcademicaId),
            );

        $this->academicAccess->constrainByAssignedProgramacion($query, $assignedMiembroId);
        $this->academicAccess->constrainByActiveEnrollment($query, $enrolledMiembroId);

        return $query
            ->orderByDesc('publicado_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(array $data): Tarea
    {
        return Tarea::query()->create($data);
    }

    public function update(Tarea $tarea, array $data): Tarea
    {
        $tarea->update($data);

        return $tarea->refresh();
    }

    public function delete(Tarea $tarea): void
    {
        $tarea->delete();
    }
}
