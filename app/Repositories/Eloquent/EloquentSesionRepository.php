<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Repositories\Contracts\SesionRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentSesionRepository implements SesionRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        $query = Sesion::query()
            ->with(['programacionAcademica.curso'])
            ->when(
                $programacionAcademicaId !== null,
                fn ($builder) => $builder->where('programacion_academica_id', $programacionAcademicaId),
            );

        $this->academicAccess->constrainByAssignedProgramacion($query, $assignedMiembroId);

        return $query
            ->orderBy('inicio_at')
            ->orderBy('fin_at')
            ->paginate($perPage);
    }

    public function create(array $data): Sesion
    {
        $leccionIds = $this->normalizeLeccionIds($data['leccion_ids'] ?? null);
        unset($data['leccion_ids']);

        $sesion = Sesion::query()->create($data);

        if ($leccionIds !== null) {
            $sesion->lecciones()->sync($leccionIds);
        }

        return $sesion->refresh()->load(['programacionAcademica.curso', 'lecciones']);
    }

    public function update(Sesion $sesion, array $data): Sesion
    {
        $leccionIds = $this->normalizeLeccionIds($data['leccion_ids'] ?? null);
        unset($data['leccion_ids']);

        $sesion->update($data);

        if ($leccionIds !== null) {
            $sesion->lecciones()->sync($leccionIds);
        }

        return $sesion->refresh()->load(['programacionAcademica.curso', 'lecciones']);
    }

    public function lockByProgramacion(ProgramacionAcademica $programacion): Collection
    {
        return Sesion::query()
            ->where('programacion_academica_id', $programacion->id)
            ->orderBy('inicio_at')
            ->orderBy('fin_at')
            ->lockForUpdate()
            ->get();
    }

    /** @return list<int>|null */
    private function normalizeLeccionIds(mixed $leccionIds): ?array
    {
        if ($leccionIds === null) {
            return null;
        }

        if (! is_array($leccionIds)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $leccionIds)));
    }
}
