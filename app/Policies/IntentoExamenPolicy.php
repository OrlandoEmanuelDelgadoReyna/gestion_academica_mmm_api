<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IntentoExamen;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class IntentoExamenPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function view(Usuario $user, IntentoExamen $intento): bool
    {
        $intento->loadMissing(['examenFinal', 'matricula']);
        $examen = $intento->examenFinal;
        if ($examen === null) {
            return false;
        }

        if ($this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id)) {
            return true;
        }

        return $user->miembro_id !== null
            && (int) $intento->matricula?->miembro_id === (int) $user->miembro_id;
    }

    public function create(Usuario $user): bool
    {
        return $this->access->hasAnyActiveEnrollment($user);
    }

    public function update(Usuario $user, IntentoExamen $intento): bool
    {
        $intento->loadMissing('matricula');

        return $user->miembro_id !== null
            && (int) $intento->matricula?->miembro_id === (int) $user->miembro_id
            && $this->access->hasAnyActiveEnrollment($user);
    }
}
