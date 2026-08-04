<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentNotificacionRepository implements NotificacionRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return Notificacion::query()
            ->with(['iglesia', 'destinatarios.usuario'])
            ->when($iglesiaId, fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function create(array $data): Notificacion
    {
        return Notificacion::query()->create($data);
    }

    public function update(Notificacion $notificacion, array $data): Notificacion
    {
        $notificacion->update($data);

        return $notificacion->refresh();
    }

    public function delete(Notificacion $notificacion): void
    {
        $notificacion->delete();
    }

    public function createDestinatarios(Notificacion $notificacion, array $usuarioIds): void
    {
        $now = now();

        foreach (array_unique($usuarioIds) as $usuarioId) {
            NotificacionDestinatario::query()->updateOrCreate(
                ['notificacion_id' => $notificacion->id, 'usuario_id' => $usuarioId],
                ['estado' => 'entregado', 'entregado_at' => $now, 'leido_at' => null],
            );
        }
    }

    public function markAsRead(Notificacion $notificacion, int $usuarioId): ?NotificacionDestinatario
    {
        $destinatario = NotificacionDestinatario::query()
            ->where('notificacion_id', $notificacion->id)
            ->where('usuario_id', $usuarioId)
            ->first();

        if ($destinatario === null) {
            return null;
        }

        $destinatario->update(['estado' => 'leido', 'leido_at' => now()]);

        return $destinatario->refresh();
    }
}
