<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Asistencia;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class AsistenciaPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, Asistencia $asistencia): bool
    {
        return $this->access->teachesAsistencia($user, $asistencia);
    }

    public function create(Usuario $user, ?Sesion $sesion = null): bool
    {
        if ($this->access->isGlobalAcademic($user)) {
            return true;
        }

        return $sesion instanceof Sesion && $this->access->teachesSesion($user, $sesion);
    }

    public function update(Usuario $user, Asistencia $asistencia): bool
    {
        return $this->access->teachesAsistencia($user, $asistencia);
    }
}
