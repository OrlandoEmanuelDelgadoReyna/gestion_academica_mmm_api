<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

final class UsuarioPolicy
{
    public function viewAny(Usuario $actor): bool
    {
        return $this->allows($actor, 'usuarios.ver');
    }

    public function view(Usuario $actor, Usuario $usuario): bool
    {
        return $this->viewAny($actor) || $actor->is($usuario);
    }

    public function create(Usuario $actor): bool
    {
        return $this->allows($actor, 'usuarios.crear');
    }

    public function update(Usuario $actor, Usuario $usuario): bool
    {
        return $this->allows($actor, 'usuarios.actualizar') && ! $actor->is($usuario);
    }

    public function delete(Usuario $actor, Usuario $usuario): bool
    {
        return $this->allows($actor, 'usuarios.eliminar') && ! $actor->is($usuario);
    }

    private function allows(Usuario $actor, string $permission): bool
    {
        return $actor->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', $permission)->where('activo', true))->exists();
    }
}
