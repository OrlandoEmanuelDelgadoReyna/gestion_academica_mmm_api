<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProgramacionAcademica;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class ProgramacionAcademicaPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, ProgramacionAcademica $programacionAcademica): bool
    {
        return $this->access->teachesProgramacion($user, $programacionAcademica);
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, ProgramacionAcademica $programacionAcademica): bool
    {
        return $this->access->isGlobalAcademic($user);
    }
}
