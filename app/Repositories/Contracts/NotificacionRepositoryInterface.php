<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface NotificacionRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    public function paginateInbox(int $usuarioId, int $perPage): LengthAwarePaginator;

    public function unreadCount(int $usuarioId): int;

    public function markAllAsRead(int $usuarioId): int;

    public function create(array $data): Notificacion;

    public function update(Notificacion $notificacion, array $data): Notificacion;

    public function delete(Notificacion $notificacion): void;

    /** @param  list<int>  $usuarioIds */
    public function createDestinatarios(Notificacion $notificacion, array $usuarioIds): void;

    public function markAsRead(Notificacion $notificacion, int $usuarioId): ?NotificacionDestinatario;

    public function existsForAnuncio(int $anuncioId): bool;

    public function deleteGeneratedByAnuncio(int $anuncioId): void;

    /** @param  list<int>  $anuncioIds */
    public function deleteGeneratedByAnuncios(array $anuncioIds): int;

    /** @return Collection<int, Notificacion> */
    public function listLegacyAnuncioNotificaciones(): Collection;

    /** @return array{notificaciones: int, destinatarios: int} */
    public function deleteLegacyAnuncioNotificaciones(): array;
}
