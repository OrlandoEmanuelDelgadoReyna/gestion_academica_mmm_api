<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProgramacionAcademica;
use App\Models\Usuario;

final class ProgramacionAcademicaPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, ProgramacionAcademica $programacionAcademica): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, ProgramacionAcademica $programacionAcademica): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
