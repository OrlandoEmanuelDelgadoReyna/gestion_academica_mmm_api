<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tarea;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class TareaPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAcademicLists($user);
    }

    public function view(Usuario $user, Tarea $tarea): bool
    {
        return $this->access->canViewProgramacionId($user, (int) $tarea->programacion_academica_id);
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, Tarea $tarea): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function delete(Usuario $user, Tarea $tarea): bool
    {
        return $this->access->isGlobalAcademic($user);
    }
}
