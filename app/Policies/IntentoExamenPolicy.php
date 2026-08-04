<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\IntentoExamen;
use App\Models\Usuario;

final class IntentoExamenPolicy
{
    public function view(Usuario $user, IntentoExamen $intento): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, IntentoExamen $intento): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
