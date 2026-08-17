<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Repositories\Contracts\SesionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentSesionRepository implements SesionRepositoryInterface
{
    public function paginate(int $perPage, ?int $programacionAcademicaId = null): LengthAwarePaginator
    {
        return Sesion::query()
            ->with(['programacionAcademica.curso'])
            ->when(
                $programacionAcademicaId !== null,
                fn ($query) => $query->where('programacion_academica_id', $programacionAcademicaId),
            )
            ->orderBy('inicio_at')
            ->orderBy('fin_at')
            ->paginate($perPage);
    }

    public function create(array $data): Sesion
    {
        $leccionIds = $data['leccion_ids'] ?? null;
        unset($data['leccion_ids']);

        $sesion = Sesion::query()->create($data);

        if ($leccionIds !== null) {
            $sesion->lecciones()->sync($leccionIds);
        }

        return $sesion->refresh()->load(['programacionAcademica.curso', 'lecciones']);
    }

    public function update(Sesion $sesion, array $data): Sesion
    {
        $leccionIds = $data['leccion_ids'] ?? null;
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
}
