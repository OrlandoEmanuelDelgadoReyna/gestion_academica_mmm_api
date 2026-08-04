<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Rol;
use App\Models\Usuario;

final class RolPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Rol $rol): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, Rol $rol): bool
    {
        return $this->allows($user);
    }

    public function delete(Usuario $user, Rol $rol): bool
    {
        return $this->allows($user) && ! $rol->usuarios()->exists();
    }

    public function restore(Usuario $user, Rol $rol): bool
    {
        return false;
    }

    public function forceDelete(Usuario $user, Rol $rol): bool
    {
        return false;
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->where('codigo', 'ADMINISTRADOR')->exists();
    }
}
