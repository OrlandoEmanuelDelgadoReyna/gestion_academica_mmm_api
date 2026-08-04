<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class NotificacionService
{
    public function __construct(
        private NotificacionRepositoryInterface $notificaciones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return $this->notificaciones->paginate($perPage, $iglesiaId);
    }

    public function create(array $data, int $actorId): Notificacion
    {
        $data['creado_por_usuario_id'] = $actorId;

        return $this->transactions->execute(function () use ($data, $actorId): Notificacion {
            $notificacion = $this->notificaciones->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'notificaciones', $notificacion->id, null, $notificacion->getAttributes());

            return $notificacion;
        });
    }

    public function update(Notificacion $notificacion, array $data, int $actorId): Notificacion
    {
        return $this->transactions->execute(function () use ($notificacion, $data, $actorId): Notificacion {
            $before = $notificacion->getAttributes();
            $updated = $this->notificaciones->update($notificacion, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'notificaciones', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(Notificacion $notificacion, int $actorId): void
    {
        $this->transactions->execute(function () use ($notificacion, $actorId): void {
            $before = $notificacion->getAttributes();
            $this->notificaciones->delete($notificacion);
            $this->auditorias->record($actorId, 'DELETE', 'notificaciones', $notificacion->id, $before, null);
        });
    }

    /** @param  list<int>  $usuarioIds */
    public function enviar(Notificacion $notificacion, array $usuarioIds, int $actorId): Notificacion
    {
        if ($notificacion->enviado_at !== null) {
            throw ValidationException::withMessages([
                'notificacion' => 'La notificación ya fue enviada.',
            ]);
        }

        if ($usuarioIds === []) {
            throw ValidationException::withMessages([
                'usuario_ids' => 'Debe indicar al menos un destinatario.',
            ]);
        }

        return $this->transactions->execute(function () use ($notificacion, $usuarioIds, $actorId): Notificacion {
            $before = $notificacion->getAttributes();
            $updated = $this->notificaciones->update($notificacion, ['enviado_at' => now()]);
            $this->notificaciones->createDestinatarios($updated, $usuarioIds);
            $this->auditorias->record($actorId, 'SEND', 'notificaciones', $updated->id, $before, $updated->fresh()->getAttributes());

            return $updated->load('destinatarios.usuario');
        });
    }

    public function marcarLeida(Notificacion $notificacion, int $usuarioId): NotificacionDestinatario
    {
        $destinatario = $this->notificaciones->markAsRead($notificacion, $usuarioId);

        if ($destinatario === null) {
            throw ValidationException::withMessages([
                'notificacion' => 'No es destinatario de esta notificación.',
            ]);
        }

        return $destinatario;
    }
}
