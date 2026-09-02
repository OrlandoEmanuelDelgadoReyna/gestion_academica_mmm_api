<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Certificado;
use App\Models\Usuario;
use App\Services\AcademicAccess;

final class CertificadoPolicy
{
    public function __construct(private AcademicAccess $access) {}

    public function viewAny(Usuario $user): bool
    {
        return $this->access->canViewCertificateLists($user);
    }

    public function view(Usuario $user, Certificado $certificado): bool
    {
        return $this->access->canViewCertificate($user, $certificado);
    }

    public function descargar(Usuario $user, Certificado $certificado): bool
    {
        return $this->view($user, $certificado);
    }

    public function emitir(Usuario $user): bool
    {
        return $this->canIssue($user);
    }

    public function revocar(Usuario $user, Certificado $certificado): bool
    {
        return $this->canIssue($user) && $this->access->canViewCertificate($user, $certificado);
    }

    public function reemplazar(Usuario $user, Certificado $certificado): bool
    {
        return $this->canIssue($user) && $this->access->canViewCertificate($user, $certificado);
    }

    public function consultarElegibilidad(Usuario $user): bool
    {
        return $this->access->canViewCertificateLists($user);
    }

    private function canIssue(Usuario $user): bool
    {
        if ($this->access->isDocente($user) && ! $this->access->isGlobalAcademic($user)) {
            return false;
        }

        return $user->roles()
            ->whereHas(
                'permisos',
                fn ($query) => $query->where('codigo', 'certificados.emitir')->where('activo', true),
            )
            ->exists();
    }
}
