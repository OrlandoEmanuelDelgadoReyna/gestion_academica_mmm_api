<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Matricula;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class MatriculaPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAcademicLists($user);
    }

    public function view(Usuario $user, Matricula $matricula): bool
    {
        if ($this->access->teachesProgramacionId($user, (int) $matricula->programacion_academica_id)) {
            return true;
        }

        return $user->miembro_id !== null
            && (int) $matricula->miembro_id === (int) $user->miembro_id
            && $matricula->estado === 'activa';
    }

    public function create(Usuario $user): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function update(Usuario $user, Matricula $matricula): bool
    {
        return $this->access->isGlobalAcademic($user);
    }
}
