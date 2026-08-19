<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/** Central academic authorization: global managers vs assigned teachers. */
final class AcademicAccess
{
    public function isGlobalAcademic(Usuario $user): bool
    {
        return $user->roles()
            ->whereHas(
                'permisos',
                fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true),
            )
            ->exists();
    }

    public function isDocente(Usuario $user): bool
    {
        return $user->roles()->where('codigo', 'DOCENTE')->exists();
    }

    public function canViewAssignedLists(Usuario $user): bool
    {
        return $this->isGlobalAcademic($user)
            || ($this->isDocente($user) && $user->miembro_id !== null);
    }

    public function teachesProgramacion(Usuario $user, ProgramacionAcademica $programacion): bool
    {
        if ($this->isGlobalAcademic($user)) {
            return true;
        }

        return $this->isAssignedToProgramacion($user, $programacion);
    }

    public function teachesProgramacionId(Usuario $user, int $programacionId): bool
    {
        if ($this->isGlobalAcademic($user)) {
            return true;
        }

        $programacion = ProgramacionAcademica::query()->find($programacionId);

        return $programacion !== null && $this->isAssignedToProgramacion($user, $programacion);
    }

    public function teachesCursoId(Usuario $user, int $cursoId): bool
    {
        if ($this->isGlobalAcademic($user)) {
            return true;
        }

        $miembroId = $user->miembro_id;
        if ($miembroId === null || ! $this->isDocente($user)) {
            return false;
        }

        return ProgramacionAcademica::query()
            ->where('curso_id', $cursoId)
            ->whereHas('docentes', fn ($query) => $query->whereKey($miembroId))
            ->exists();
    }

    public function teachesSesion(Usuario $user, Sesion $sesion): bool
    {
        if ($this->isGlobalAcademic($user)) {
            return true;
        }

        return $this->isAssignedToProgramacionId($user, (int) $sesion->programacion_academica_id);
    }

    public function teachesAsistencia(Usuario $user, Asistencia $asistencia): bool
    {
        if ($this->isGlobalAcademic($user)) {
            return true;
        }

        $sesion = $asistencia->relationLoaded('sesion')
            ? $asistencia->sesion
            : $asistencia->sesion()->first();

        return $sesion instanceof Sesion && $this->teachesSesion($user, $sesion);
    }

    /**
     * Null keeps the current unscoped listing (global academic).
     * A member id restricts listings to assigned programaciones.
     */
    public function listScopeMiembroId(Usuario $user): ?int
    {
        if ($this->isGlobalAcademic($user)) {
            return null;
        }

        return $user->miembro_id !== null ? (int) $user->miembro_id : null;
    }

    public function constrainProgramaciones(Builder $query, ?int $assignedMiembroId): void
    {
        if ($assignedMiembroId === null) {
            return;
        }

        $query->whereIn('id', $this->assignedProgramacionIdsQuery($assignedMiembroId));
    }

    public function constrainByAssignedProgramacion(Builder $query, ?int $assignedMiembroId, string $column = 'programacion_academica_id'): void
    {
        if ($assignedMiembroId === null) {
            return;
        }

        $query->whereIn($column, $this->assignedProgramacionIdsQuery($assignedMiembroId));
    }

    public function constrainAsistencias(Builder $query, ?int $assignedMiembroId): void
    {
        if ($assignedMiembroId === null) {
            return;
        }

        $query->whereIn('sesion_id', function ($sub) use ($assignedMiembroId): void {
            $sub->select('sesiones.id')
                ->from('sesiones')
                ->join(
                    'programacion_docentes',
                    'programacion_docentes.programacion_academica_id',
                    '=',
                    'sesiones.programacion_academica_id',
                )
                ->where('programacion_docentes.miembro_id', $assignedMiembroId);
        });
    }

    private function isAssignedToProgramacion(Usuario $user, ProgramacionAcademica $programacion): bool
    {
        return $this->isAssignedToProgramacionId($user, $programacion->id);
    }

    private function isAssignedToProgramacionId(Usuario $user, int $programacionId): bool
    {
        $miembroId = $user->miembro_id;
        if ($miembroId === null) {
            return false;
        }

        return ProgramacionAcademica::query()
            ->whereKey($programacionId)
            ->whereHas('docentes', fn ($query) => $query->whereKey($miembroId))
            ->exists();
    }

    /** @return \Closure */
    private function assignedProgramacionIdsQuery(int $miembroId): \Closure
    {
        return function ($sub) use ($miembroId): void {
            $sub->select('programacion_academica_id')
                ->from('programacion_docentes')
                ->where('miembro_id', $miembroId);
        };
    }
}
