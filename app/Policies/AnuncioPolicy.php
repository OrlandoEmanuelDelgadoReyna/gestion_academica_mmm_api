<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Anuncio;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use Illuminate\Auth\Access\Response;

final class AnuncioPolicy
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function viewAny(Usuario $user): Response
    {
        return $this->manageResponse($user);
    }

    public function viewPublicados(Usuario $user): Response
    {
        if ($this->academicAccess->iglesiaId($user) === null) {
            return Response::deny('No tiene una iglesia asignada para consultar anuncios.');
        }

        return Response::allow();
    }

    public function view(Usuario $user, Anuncio $anuncio): Response
    {
        if ($this->canManageChurch($user, (int) $anuncio->iglesia_id)) {
            return Response::allow();
        }

        if ($anuncio->isVigente() && $this->academicAccess->belongsToIglesia($user, (int) $anuncio->iglesia_id)) {
            return Response::allow();
        }

        return Response::deny('No puede consultar este anuncio.');
    }

    public function create(Usuario $user): Response
    {
        return $this->manageResponse($user);
    }

    public function update(Usuario $user, Anuncio $anuncio): Response
    {
        return $this->manageResponse($user, (int) $anuncio->iglesia_id, 'No puede modificar anuncios de otra iglesia.');
    }

    public function delete(Usuario $user, Anuncio $anuncio): Response
    {
        return $this->manageResponse($user, (int) $anuncio->iglesia_id, 'No puede eliminar anuncios de otra iglesia.');
    }

    private function manageResponse(
        Usuario $user,
        ?int $iglesiaId = null,
        string $denial = 'No tiene permiso para gestionar anuncios.',
    ): Response {
        if (! $this->canManageChurch($user, $iglesiaId)) {
            return Response::deny($denial);
        }

        return Response::allow();
    }

    private function canManageChurch(Usuario $user, ?int $iglesiaId = null): bool
    {
        if (! $this->academicAccess->isGlobalAcademic($user)) {
            return false;
        }

        $own = $this->academicAccess->iglesiaId($user);
        if ($own === null) {
            return false;
        }

        return $iglesiaId === null || $own === $iglesiaId;
    }
}
