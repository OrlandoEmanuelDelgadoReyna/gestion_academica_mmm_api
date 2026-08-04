<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EntregaTarea;
use App\Models\Usuario;

final class EntregaTareaPolicy
{
    public function view(Usuario $user, EntregaTarea $entrega): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, EntregaTarea $entrega): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
