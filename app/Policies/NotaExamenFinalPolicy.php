<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\NotaExamenFinal;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class NotaExamenFinalPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAssignedLists($user);
    }

    public function view(Usuario $user, NotaExamenFinal $nota): bool
    {
        $nota->loadMissing(['examenFinal', 'matricula']);
        $examen = $nota->examenFinal;
        if ($examen === null) {
            return false;
        }

        if ($this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id)) {
            return true;
        }

        return $user->miembro_id !== null
            && (int) $nota->matricula?->miembro_id === (int) $user->miembro_id;
    }

    public function grade(Usuario $user, NotaExamenFinal $nota): bool
    {
        $nota->loadMissing('examenFinal');
        $examen = $nota->examenFinal;
        if ($examen === null) {
            return false;
        }

        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }
}
