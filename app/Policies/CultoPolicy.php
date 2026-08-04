<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Culto;
use App\Models\Usuario;

final class CultoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Culto $culto): bool
    {
        return $this->allows($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function update(Usuario $user, Culto $culto): bool
    {
        return $this->allows($user);
    }

    public function delete(Usuario $user, Culto $culto): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'cultos.gestionar')->where('activo', true))->exists();
    }
}
