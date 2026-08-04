<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notificacion;
use App\Models\Usuario;

final class NotificacionPolicy
{
    public function viewAny(Usuario $user): bool
    {
        return $this->allowsManage($user);
    }

    public function view(Usuario $user, Notificacion $notificacion): bool
    {
        return $this->allowsManage($user);
    }

    public function create(Usuario $user): bool
    {
        return $this->allowsManage($user);
    }

    public function update(Usuario $user, Notificacion $notificacion): bool
    {
        return $this->allowsManage($user);
    }

    public function delete(Usuario $user, Notificacion $notificacion): bool
    {
        return $this->allowsManage($user);
    }

    public function enviar(Usuario $user, Notificacion $notificacion): bool
    {
        return $this->allowsManage($user);
    }

    public function marcarLeida(Usuario $user, Notificacion $notificacion): bool
    {
        return $notificacion->destinatarios()->where('usuario_id', $user->id)->exists();
    }

    private function allowsManage(Usuario $user): bool
    {
        return $user->roles()->whereHas('permisos', fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true))->exists();
    }
}
