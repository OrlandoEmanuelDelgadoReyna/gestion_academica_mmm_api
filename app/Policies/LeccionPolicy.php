<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Leccion;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class LeccionPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, Leccion $leccion): bool
    {
        return $this->access->teachesCursoId($user, (int) $leccion->curso_id);
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, Leccion $leccion): bool
    {
        return $this->access->isGlobalAcademic($user);
    }
}
