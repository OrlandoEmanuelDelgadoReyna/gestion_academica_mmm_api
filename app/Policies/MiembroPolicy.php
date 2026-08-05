<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Miembro;
use App\Models\Usuario;

final class MiembroPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Miembro $miembro): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, Miembro $miembro): bool
    {
        return $this->allows($user);
    }

    public function delete(Usuario $user, Miembro $miembro): bool
    {
        return $user->roles()->whereHas(
            'permisos',
            fn ($q) => $q->where('codigo', 'miembros.gestionar')->where('activo', true),
        )->exists();
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($q) => $q->whereIn('codigo', ['miembros.ver', 'miembros.gestionar'])->where('activo', true))->exists();
    }
}
