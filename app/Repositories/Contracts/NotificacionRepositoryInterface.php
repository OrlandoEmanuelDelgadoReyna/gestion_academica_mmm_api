<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Notificacion;
use App\Models\NotificacionDestinatario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface NotificacionRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator;

    public function create(array $data): Notificacion;

    public function update(Notificacion $notificacion, array $data): Notificacion;

    public function delete(Notificacion $notificacion): void;

    /** @param  list<int>  $usuarioIds */
    public function createDestinatarios(Notificacion $notificacion, array $usuarioIds): void;

    public function markAsRead(Notificacion $notificacion, int $usuarioId): ?NotificacionDestinatario;
}
