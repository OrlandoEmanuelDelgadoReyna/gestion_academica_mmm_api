<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Certificado;
use App\Models\Usuario;

final class CertificadoPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function view(Usuario $user, Certificado $certificado): bool
    {
        return $this->allows($user);
    }

    public function emitir(Usuario $user): bool
    {
        return $this->allows($user);
    }

    public function revocar(Usuario $user, Certificado $certificado): bool
    {
        return $this->allows($user);
    }

    public function reemplazar(Usuario $user, Certificado $certificado): bool
    {
        return $this->allows($user);
    }

    private function allows(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'certificados.emitir')->where('activo', true))->exists();
    }
}
