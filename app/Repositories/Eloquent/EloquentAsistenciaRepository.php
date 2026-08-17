<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Sesion;
use App\Repositories\Contracts\AsistenciaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentAsistenciaRepository implements AsistenciaRepositoryInterface
{
    public function paginate(int $perPage, ?int $sesionId = null): LengthAwarePaginator
    {
        return Asistencia::query()
            ->with(['sesion', 'matricula.miembro'])
            ->when(
                $sesionId !== null,
                fn ($query) => $query->where('sesion_id', $sesionId),
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): Asistencia
    {
        return Asistencia::query()->create($data);
    }

    public function update(Asistencia $asistencia, array $data): Asistencia
    {
        $asistencia->update($data);

        return $asistencia->refresh();
    }

    public function existsForSessionAndEnrollment(Sesion $sesion, Matricula $matricula, ?int $exceptId = null): bool
    {
        return Asistencia::query()
            ->where('sesion_id', $sesion->id)
            ->where('matricula_id', $matricula->id)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->exists();
    }
}
