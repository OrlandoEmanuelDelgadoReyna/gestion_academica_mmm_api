<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Certificado;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;

/** Central academic authorization: global managers, assigned teachers, enrolled students. */
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

    public function iglesiaId(Usuario $user): ?int
    {
        if ($user->relationLoaded('miembro')) {
            return $user->miembro?->iglesia_id !== null ? (int) $user->miembro->iglesia_id : null;
        }

        if ($user->miembro_id === null) {
            return null;
        }

        $iglesiaId = Miembro::query()->whereKey($user->miembro_id)->value('iglesia_id');

        return $iglesiaId !== null ? (int) $iglesiaId : null;
    }

    public function belongsToIglesia(Usuario $user, int $iglesiaId): bool
    {
        $own = $this->iglesiaId($user);

        return $own !== null && $own === $iglesiaId;
    }

    /** @return list<int> */
    public function activeUsuarioIdsOfIglesia(int $iglesiaId): array
    {
        return Usuario::query()
            ->where('activo', true)
            ->whereHas('miembro', fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public function constrainUsuarioIdsToIglesia(int $iglesiaId, array $usuarioIds): array
    {
        $usuarioIds = array_values(array_unique(array_filter($usuarioIds)));
        if ($usuarioIds === []) {
            return [];
        }

        return Usuario::query()
            ->whereIn('id', $usuarioIds)
            ->where('activo', true)
            ->whereHas('miembro', fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->pluck('id')
            ->all();
    }

    public function canViewAssignedLists(Usuario $user): bool
    {
        return $this->isGlobalAcademic($user)
            || ($this->isDocente($user) && $user->miembro_id !== null);
    }

    public function canViewAcademicLists(Usuario $user): bool
    {
        return $this->canViewAssignedLists($user) || $this->hasAnyActiveEnrollment($user);
    }

    /**
     * Active enrollment of the authenticated member in a programming.
     * Does not use programacion_docentes or historical enrollment rows.
     */
    public function hasActiveEnrollment(Usuario $user, int $programacionAcademicaId): bool
    {
        $miembroId = $user->miembro_id;
        if ($miembroId === null) {
            return false;
        }

        return Matricula::query()
            ->where('miembro_id', $miembroId)
            ->where('programacion_academica_id', $programacionAcademicaId)
            ->where('estado', 'activa')
            ->exists();
    }

    public function hasAnyActiveEnrollment(Usuario $user): bool
    {
        $miembroId = $user->miembro_id;
        if ($miembroId === null) {
            return false;
        }

        return Matricula::query()
            ->where('miembro_id', $miembroId)
            ->where('estado', 'activa')
            ->exists();
    }

    public function canViewProgramacionId(Usuario $user, int $programacionId): bool
    {
        return $this->teachesProgramacionId($user, $programacionId)
            || $this->hasActiveEnrollment($user, $programacionId);
    }

    /** Teacher list scope. Null means unscoped (global academic). Never used for students. */
    public function teacherListMiembroId(Usuario $user): ?int
    {
        if ($this->isGlobalAcademic($user) || ! $this->isDocente($user)) {
            return null;
        }

        return $user->miembro_id !== null ? (int) $user->miembro_id : null;
    }

    /** Student list scope. Null unless the user is a non-teacher, non-manager member. */
    public function studentListMiembroId(Usuario $user): ?int
    {
        if ($this->isGlobalAcademic($user) || $this->isDocente($user)) {
            return null;
        }

        return $user->miembro_id !== null ? (int) $user->miembro_id : null;
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

    public function constrainByActiveEnrollment(Builder $query, ?int $enrolledMiembroId, string $column = 'programacion_academica_id'): void
    {
        if ($enrolledMiembroId === null) {
            return;
        }

        $query->whereIn($column, $this->enrolledProgramacionIdsQuery($enrolledMiembroId));
    }

    public function constrainOwnActiveMatriculas(Builder $query, ?int $enrolledMiembroId): void
    {
        if ($enrolledMiembroId === null) {
            return;
        }

        $query->where('miembro_id', $enrolledMiembroId)->where('estado', 'activa');
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

    public function isAssignedToProgramacion(Usuario $user, ProgramacionAcademica $programacion): bool
    {
        return $this->isAssignedToProgramacionId($user, $programacion->id);
    }

    public function isAssignedToProgramacionId(Usuario $user, int $programacionId): bool
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

    /** @return \Closure */
    private function enrolledProgramacionIdsQuery(int $miembroId): \Closure
    {
        return function ($sub) use ($miembroId): void {
            $sub->select('programacion_academica_id')
                ->from('matriculas')
                ->where('miembro_id', $miembroId)
                ->where('estado', 'activa');
        };
    }

    public function canViewCertificateLists(Usuario $user): bool
    {
        return $this->isGlobalAcademic($user)
            || ($this->isDocente($user) && $user->miembro_id !== null)
            || $user->miembro_id !== null;
    }

    public function constrainCertificados(Builder $query, Usuario $user): void
    {
        if ($this->isGlobalAcademic($user)) {
            $iglesiaId = $this->iglesiaId($user);
            if ($iglesiaId === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereHas('miembro', fn ($sub) => $sub->where('iglesia_id', $iglesiaId));

            return;
        }

        if ($this->isDocente($user) && $user->miembro_id !== null) {
            $this->constrainByAssignedProgramacion($query, (int) $user->miembro_id);

            return;
        }

        if ($user->miembro_id !== null) {
            $query->where('miembro_id', $user->miembro_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }

    public function canViewCertificate(Usuario $user, Certificado $certificado): bool
    {
        if ($user->miembro_id !== null && (int) $certificado->miembro_id === (int) $user->miembro_id) {
            if ($certificado->programacion_academica_id === null) {
                return true;
            }

            return $this->hasAnyEnrollment($user, (int) $certificado->programacion_academica_id);
        }

        if ($this->isGlobalAcademic($user)) {
            $iglesiaId = $this->iglesiaId($user);
            if ($iglesiaId === null) {
                return false;
            }

            $memberChurch = $certificado->relationLoaded('miembro')
                ? $certificado->miembro?->iglesia_id
                : Miembro::query()->whereKey($certificado->miembro_id)->value('iglesia_id');

            return $memberChurch !== null && (int) $memberChurch === $iglesiaId;
        }

        if ($this->isDocente($user) && $certificado->programacion_academica_id !== null) {
            return $this->isAssignedToProgramacionId($user, (int) $certificado->programacion_academica_id);
        }

        return false;
    }

    public function canIssueCertificatesForMember(Usuario $user, int $miembroId): bool
    {
        if (! $this->isGlobalAcademic($user)) {
            return false;
        }

        $iglesiaId = $this->iglesiaId($user);
        if ($iglesiaId === null) {
            return false;
        }

        $memberChurch = Miembro::query()->whereKey($miembroId)->value('iglesia_id');

        return $memberChurch !== null && (int) $memberChurch === $iglesiaId;
    }

    public function canConsultarElegibilidad(Usuario $user, int $miembroId, int $programacionId): bool
    {
        if ($user->miembro_id !== null && (int) $user->miembro_id === $miembroId) {
            return $this->hasAnyEnrollment($user, $programacionId);
        }

        if ($this->isGlobalAcademic($user)) {
            return $this->canIssueCertificatesForMember($user, $miembroId);
        }

        return $this->isDocente($user) && $this->isAssignedToProgramacionId($user, $programacionId);
    }

    public function hasAnyEnrollment(Usuario $user, int $programacionAcademicaId): bool
    {
        $miembroId = $user->miembro_id;
        if ($miembroId === null) {
            return false;
        }

        return Matricula::query()
            ->where('miembro_id', $miembroId)
            ->where('programacion_academica_id', $programacionAcademicaId)
            ->exists();
    }
}
