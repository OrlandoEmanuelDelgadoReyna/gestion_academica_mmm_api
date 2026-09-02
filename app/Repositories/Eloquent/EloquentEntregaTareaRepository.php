<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\EntregaTarea;
use App\Repositories\Contracts\EntregaTareaRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentEntregaTareaRepository implements EntregaTareaRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginate(
        int $perPage,
        ?int $tareaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator {
        $query = EntregaTarea::query()
            ->with(['tarea', 'matricula.miembro'])
            ->when($tareaId !== null, fn ($builder) => $builder->where('tarea_id', $tareaId));

        if ($assignedMiembroId !== null) {
            $query->whereHas(
                'tarea',
                fn ($builder) => $this->academicAccess->constrainByAssignedProgramacion($builder, $assignedMiembroId),
            );
        }

        if ($enrolledMiembroId !== null) {
            $query->whereHas(
                'matricula',
                fn ($builder) => $builder->where('miembro_id', $enrolledMiembroId)->where('estado', 'activa'),
            );
        }

        return $query
            ->orderByDesc('entregado_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(array $data): EntregaTarea
    {
        return EntregaTarea::query()->create($data);
    }

    public function update(EntregaTarea $entrega, array $data): EntregaTarea
    {
        $entrega->update($data);

        return $entrega->refresh();
    }
}
