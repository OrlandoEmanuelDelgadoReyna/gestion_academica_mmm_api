<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Iglesia;
use App\Models\Usuario;

final class IglesiaPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allowed($user, 'iglesias.ver');
    }

    public function view(Usuario $user, Iglesia $iglesia): bool
    {
        return $this->viewAny($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allowed($user, 'iglesias.crear');
    }

    public function update(Usuario $user, Iglesia $iglesia): bool
    {
        return $this->allowed($user, 'iglesias.actualizar');
    }

    private function allowed(Usuario $user, string $permission): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', $permission)->where('activo', true))->exists();
    }
}
