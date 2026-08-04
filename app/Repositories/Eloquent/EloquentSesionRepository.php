<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Sesion;
use App\Repositories\Contracts\SesionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentSesionRepository implements SesionRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Sesion::query()
            ->with(['programacionAcademica.curso'])
            ->orderBy('inicio_at')
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
}
