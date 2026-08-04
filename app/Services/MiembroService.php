<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Miembro;
use App\Models\TransicionEstadoMembresia;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\MiembroRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Enforces membership lifecycle transitions atomically. */
final class MiembroService
{
    public function __construct(private MiembroRepositoryInterface $miembros, private DatabaseTransactionRepositoryInterface $transactions, private AuditoriaRepositoryInterface $auditorias) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->miembros->paginate($perPage);
    }

    public function create(array $attributes, int $actorId): Miembro
    {
        return $this->transactions->execute(function () use ($attributes, $actorId): Miembro {
            $miembro = $this->miembros->create($attributes);
            $this->auditorias->record($actorId, 'CREATE', 'miembros', $miembro->id, null, $miembro->getAttributes());

            return $miembro;
        });
    }

    public function update(Miembro $miembro, array $attributes, int $actorId): Miembro
    {
        return $this->transactions->execute(function () use ($miembro, $attributes, $actorId): Miembro {
            $before = $miembro->getAttributes();
            $updated = $this->miembros->update($miembro, $attributes);
            $this->auditorias->record($actorId, 'UPDATE', 'miembros', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function transition(Miembro $miembro, int $destinationId, string $date, ?string $observation, int $actorId): void
    {
        $this->transactions->execute(function () use ($miembro, $destinationId, $date, $observation, $actorId): void {
            $originId = $this->miembros->currentMembershipState($miembro);
            if ($originId !== null) {
                $transition = TransicionEstadoMembresia::query()->where('estado_origen_id', $originId)->where('estado_destino_id', $destinationId)->where('activo', true)->first();
                if ($transition === null || ($transition->requiere_observacion && blank($observation))) {
                    throw ValidationException::withMessages(['estado_membresia_id' => 'La transición de membresía no está permitida.']);
                }
            } $this->miembros->transition($miembro, $destinationId, $date, $observation, $actorId);
            $this->auditorias->record($actorId, 'MEMBERSHIP_TRANSITION', 'historial_membresia', $miembro->id, ['estado_id' => $originId], ['estado_id' => $destinationId]);
        });
    }
}
