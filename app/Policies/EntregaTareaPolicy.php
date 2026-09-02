<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EntregaTarea;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class EntregaTareaPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAcademicLists($user);
    }

    public function view(Usuario $user, EntregaTarea $entrega): bool
    {
        $entrega->loadMissing(['tarea', 'matricula']);
        $tarea = $entrega->tarea;
        if ($tarea === null) {
            return false;
        }

        if (! $this->access->canViewProgramacionId($user, (int) $tarea->programacion_academica_id)) {
            return false;
        }

        if ($this->access->isGlobalAcademic($user)
            || $this->access->teachesProgramacionId($user, (int) $tarea->programacion_academica_id)
        ) {
            return true;
        }

        return $user->miembro_id !== null
            && (int) $entrega->matricula?->miembro_id === (int) $user->miembro_id;
    }

    public function create(Usuario $user): bool
    {
        return $this->access->hasAnyActiveEnrollment($user);
    }

    public function update(Usuario $user, EntregaTarea $entrega): bool
    {
        $entrega->loadMissing(['tarea', 'matricula']);
        $tarea = $entrega->tarea;
        if ($tarea === null) {
            return false;
        }

        if ($user->miembro_id === null
            || (int) $entrega->matricula?->miembro_id !== (int) $user->miembro_id
        ) {
            return false;
        }

        return $this->access->hasActiveEnrollment($user, (int) $tarea->programacion_academica_id);
    }

    public function grade(Usuario $user, EntregaTarea $entrega): bool
    {
        $entrega->loadMissing('tarea');
        $tarea = $entrega->tarea;
        if ($tarea === null) {
            return false;
        }

        return $this->access->teachesProgramacionId($user, (int) $tarea->programacion_academica_id);
    }
}
