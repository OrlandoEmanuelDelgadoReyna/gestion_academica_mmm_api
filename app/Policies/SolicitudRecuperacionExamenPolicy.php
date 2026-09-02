<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class SolicitudRecuperacionExamenPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function view(Usuario $user, SolicitudRecuperacionExamen $solicitud): bool
    {
        $solicitud->loadMissing(['examenFinal', 'matricula']);
        $examen = $solicitud->examenFinal;
        if ($examen === null) {
            return false;
        }

        if ($this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id)) {
            return true;
        }

        return $user->miembro_id !== null
            && (int) $solicitud->matricula?->miembro_id === (int) $user->miembro_id;
    }

    public function create(Usuario $user): bool
    {
        return $this->access->hasAnyActiveEnrollment($user);
    }

    public function update(Usuario $user, SolicitudRecuperacionExamen $solicitud): bool
    {
        $solicitud->loadMissing('examenFinal');
        $examen = $solicitud->examenFinal;
        if ($examen === null) {
            return false;
        }

        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }
}
