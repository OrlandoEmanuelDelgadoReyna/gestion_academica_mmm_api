<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Usuario;

final class ReportePolicy
{
    public function academicos(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function administrativos(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function certificados(Usuario $user): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'auditoria.ver')->where('activo', true))->exists();
    }
}
