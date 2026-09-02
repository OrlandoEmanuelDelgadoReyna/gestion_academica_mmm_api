<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notificacion;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use Illuminate\Auth\Access\Response;

final class NotificacionPolicy
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function viewAny(Usuario $user): Response
    {
        return $this->manageResponse($user);
    }

    public function viewInbox(Usuario $user): bool
    {
        return true;
    }

    public function view(Usuario $user, Notificacion $notificacion): Response
    {
        return $this->manageResponse($user, (int) $notificacion->iglesia_id, 'No puede consultar notificaciones de otra iglesia.');
    }

    public function create(Usuario $user): Response
    {
        return $this->manageResponse($user);
    }

    public function update(Usuario $user, Notificacion $notificacion): Response
    {
        return $this->manageResponse($user, (int) $notificacion->iglesia_id, 'No puede modificar notificaciones de otra iglesia.');
    }

    public function delete(Usuario $user, Notificacion $notificacion): Response
    {
        return $this->manageResponse($user, (int) $notificacion->iglesia_id, 'No puede eliminar notificaciones de otra iglesia.');
    }

    public function enviar(Usuario $user, Notificacion $notificacion): Response
    {
        return $this->manageResponse($user, (int) $notificacion->iglesia_id, 'No puede enviar notificaciones de otra iglesia.');
    }

    public function marcarLeida(Usuario $user, Notificacion $notificacion): Response
    {
        if ($notificacion->destinatarios()->where('usuario_id', $user->id)->exists()) {
            return Response::allow();
        }

        return Response::deny('No puede marcar como leída una notificación que no le corresponde.');
    }

    public function marcarTodas(Usuario $user): bool
    {
        return true;
    }

    private function manageResponse(
        Usuario $user,
        ?int $iglesiaId = null,
        string $denial = 'No tiene permiso para gestionar notificaciones.',
    ): Response {
        if (! $this->academicAccess->isGlobalAcademic($user)) {
            return Response::deny($denial);
        }

        $own = $this->academicAccess->iglesiaId($user);
        if ($own === null) {
            return Response::deny('No tiene una iglesia asignada.');
        }

        if ($iglesiaId !== null && $own !== $iglesiaId) {
            return Response::deny($denial);
        }

        return Response::allow();
    }
}
