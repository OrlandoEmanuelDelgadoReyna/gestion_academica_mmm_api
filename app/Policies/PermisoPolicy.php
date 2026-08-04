<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permiso;
use App\Models\Usuario;

final class PermisoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Permiso $permiso): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, Permiso $permiso): bool
    {
        return $this->allows($user);
    }

    public function delete(Usuario $user, Permiso $permiso): bool
    {
        return $this->allows($user) && ! $permiso->roles()->exists();
    }

    public function restore(Usuario $user, Permiso $permiso): bool
    {
        return false;
    }

    public function forceDelete(Usuario $user, Permiso $permiso): bool
    {
        return false;
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->where('codigo', 'ADMINISTRADOR')->exists();
    }
}
