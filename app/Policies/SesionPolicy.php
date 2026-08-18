<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class SesionPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, Sesion $sesion): bool
    {
        return $this->access->teachesSesion($user, $sesion);
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, Sesion $sesion): bool
    {
        return $this->access->teachesSesion($user, $sesion);
    }
}
