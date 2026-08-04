<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Calificacion;
use App\Models\Usuario;

final class CalificacionPolicy
{
    public function view(Usuario $user, Calificacion $calificacion): bool
    {
        return $this->allows($user);
    }

    public function calcular(Usuario $user): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
