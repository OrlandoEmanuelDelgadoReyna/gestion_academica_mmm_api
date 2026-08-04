<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Evento;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\EventoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EventoService
{
    public function __construct(
        private EventoRepositoryInterface $eventos,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return $this->eventos->paginate($perPage, $iglesiaId);
    }

    public function create(array $data, int $actorId): Evento
    {
        $data['creado_por_usuario_id'] = $actorId;

        return $this->transactions->execute(function () use ($data, $actorId): Evento {
            $evento = $this->eventos->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'eventos', $evento->id, null, $evento->getAttributes());

            return $evento;
        });
    }

    public function update(Evento $evento, array $data, int $actorId): Evento
    {
        return $this->transactions->execute(function () use ($evento, $data, $actorId): Evento {
            $before = $evento->getAttributes();
            $updated = $this->eventos->update($evento, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'eventos', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(Evento $evento, int $actorId): void
    {
        $this->transactions->execute(function () use ($evento, $actorId): void {
            $before = $evento->getAttributes();
            $this->eventos->delete($evento);
            $this->auditorias->record($actorId, 'DELETE', 'eventos', $evento->id, $before, null);
        });
    }
}
