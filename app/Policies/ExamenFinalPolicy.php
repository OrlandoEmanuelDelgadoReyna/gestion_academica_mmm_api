<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExamenFinal;
use App\Models\ProgramacionAcademica;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class ExamenFinalPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewAcademicLists($user);
    }

    public function view(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->canViewProgramacionId($user, (int) $examen->programacion_academica_id);
    }

    public function create(Usuario $user, mixed $programacion = null): bool
    {
        if ($programacion instanceof ProgramacionAcademica) {
            return $this->access->teachesProgramacion($user, $programacion);
        }

        return $this->access->canViewAssignedLists($user);
    }

    public function update(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }

    public function delete(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }

    public function manageQuestions(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->isGlobalAcademic($user);
    }

    public function generarRecuperacion(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }

    public function grade(Usuario $user, ExamenFinal $examen): bool
    {
        return $this->access->teachesProgramacionId($user, (int) $examen->programacion_academica_id);
    }
}
