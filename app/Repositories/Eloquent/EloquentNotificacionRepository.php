<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use App\Repositories\Contracts\NotificacionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    public function paginateInbox(int $usuarioId, int $perPage): LengthAwarePaginator
    {
        return NotificacionDestinatario::query()
            ->select('notificacion_destinatarios.*')
            ->join('notificaciones', 'notificaciones.id', '=', 'notificacion_destinatarios.notificacion_id')
            ->with('notificacion')
            ->where('notificacion_destinatarios.usuario_id', $usuarioId)
            ->orderByRaw('CASE WHEN notificacion_destinatarios.leido_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('notificaciones.enviado_at')
            ->orderByDesc('notificacion_destinatarios.id')
            ->paginate($perPage);
    }

    public function unreadCount(int $usuarioId): int
    {
        return NotificacionDestinatario::query()
            ->where('usuario_id', $usuarioId)
            ->whereNull('leido_at')
            ->count();
    }

    public function markAllAsRead(int $usuarioId): int
    {
        return NotificacionDestinatario::query()
            ->where('usuario_id', $usuarioId)
            ->whereNull('leido_at')
            ->update([
                'estado' => 'leido',
                'leido_at' => now(),
                'updated_at' => now(),
            ]);
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
            NotificacionDestinatario::query()->firstOrCreate(
                ['notificacion_id' => $notificacion->id, 'usuario_id' => $usuarioId],
                ['estado' => 'entregado', 'entregado_at' => $now, 'leido_at' => null],
            );
        }
    }

    public function deleteDestinatariosNotIn(Notificacion $notificacion, array $usuarioIds): void
    {
        $query = NotificacionDestinatario::query()->where('notificacion_id', $notificacion->id);
        if ($usuarioIds === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('usuario_id', $usuarioIds)->delete();
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

    public function findByAnuncioId(int $anuncioId): ?Notificacion
    {
        return Notificacion::query()->where('anuncio_id', $anuncioId)->first();
    }

    public function existsForAnuncio(int $anuncioId): bool
    {
        return $this->findByAnuncioId($anuncioId) !== null;
    }

    public function deleteGeneratedByAnuncio(int $anuncioId): void
    {
        $ids = Notificacion::query()->where('anuncio_id', $anuncioId)->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }

        NotificacionDestinatario::query()->whereIn('notificacion_id', $ids)->delete();
        Notificacion::query()->whereIn('id', $ids)->delete();
    }

    public function deleteGeneratedByAnuncios(array $anuncioIds): int
    {
        $anuncioIds = array_values(array_unique(array_filter($anuncioIds)));
        if ($anuncioIds === []) {
            return 0;
        }

        $ids = Notificacion::query()
            ->where('tipo', 'anuncio')
            ->whereIn('anuncio_id', $anuncioIds)
            ->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        NotificacionDestinatario::query()->whereIn('notificacion_id', $ids)->delete();
        Notificacion::query()->whereIn('id', $ids)->delete();

        return $ids->count();
    }

    public function listLegacyAnuncioNotificaciones(): Collection
    {
        return Notificacion::query()
            ->where('tipo', 'anuncio')
            ->whereNull('anuncio_id')
            ->orderBy('id')
            ->get(['id', 'titulo', 'iglesia_id', 'enviado_at']);
    }

    public function deleteLegacyAnuncioNotificaciones(): array
    {
        $ids = Notificacion::query()
            ->where('tipo', 'anuncio')
            ->whereNull('anuncio_id')
            ->pluck('id');
        if ($ids->isEmpty()) {
            return ['notificaciones' => 0, 'destinatarios' => 0];
        }

        $destinatarios = NotificacionDestinatario::query()
            ->whereIn('notificacion_id', $ids)
            ->delete();
        $notificaciones = Notificacion::query()->whereIn('id', $ids)->delete();

        return [
            'notificaciones' => (int) $notificaciones,
            'destinatarios' => (int) $destinatarios,
        ];
    }
}
