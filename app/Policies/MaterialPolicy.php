<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Material;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class MaterialPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, Material $material): bool
    {
        return $this->access->teachesProgramacionId($user, (int) $material->programacion_academica_id);
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, Material $material): bool
    {
        return $this->access->isGlobalAcademic($user);
    }
}
