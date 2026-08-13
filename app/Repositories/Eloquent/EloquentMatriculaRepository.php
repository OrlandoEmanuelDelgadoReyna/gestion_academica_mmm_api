<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Repositories\Contracts\MatriculaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentMatriculaRepository implements MatriculaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Matricula::query()
            ->with([
                'programacionAcademica.curso',
                'programacionAcademica.horarios',
                'programacionAcademica.docentes',
                'miembro',
            ])
            ->orderByDesc('fecha_matricula')
            ->paginate($perPage);
    }

    public function create(array $data): Matricula
    {
        return Matricula::query()->create($data);
    }

    public function update(Matricula $matricula, array $data): Matricula
    {
        $matricula->update($data);

        return $matricula->refresh();
    }

    public function countEnrollments(ProgramacionAcademica $programacion): int
    {
        return Matricula::query()
            ->where('programacion_academica_id', $programacion->id)
            ->lockForUpdate()
            ->count();
    }

    public function existsForMember(ProgramacionAcademica $programacion, Miembro $miembro): bool
    {
        return Matricula::query()
            ->where('programacion_academica_id', $programacion->id)
            ->where('miembro_id', $miembro->id)
            ->exists();
    }

    public function hasScheduleConflict(Miembro $miembro, ProgramacionAcademica $programacion): bool
    {
        $targetSesiones = Sesion::query()
            ->where('programacion_academica_id', $programacion->id)
            ->get(['inicio_at', 'fin_at']);

        if ($targetSesiones->isEmpty()) {
            return false;
        }

        $otherProgramacionIds = Matricula::query()
            ->where('miembro_id', $miembro->id)
            ->where('estado', 'activa')
            ->where('programacion_academica_id', '!=', $programacion->id)
            ->pluck('programacion_academica_id');

        if ($otherProgramacionIds->isEmpty()) {
            return false;
        }

        foreach ($targetSesiones as $target) {
            $conflict = Sesion::query()
                ->whereIn('programacion_academica_id', $otherProgramacionIds)
                ->where('inicio_at', '<', $target->fin_at)
                ->where('fin_at', '>', $target->inicio_at)
                ->exists();

            if ($conflict) {
                return true;
            }
        }

        return false;
    }

    public function updateEstado(Matricula $matricula, string $estado): Matricula
    {
        $matricula->update(['estado' => $estado]);

        return $matricula->refresh();
    }
}
