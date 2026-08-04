<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BloqueCulto;
use App\Models\ParticipacionCulto;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\ParticipacionCultoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class ParticipacionCultoService
{
    public function __construct(
        private ParticipacionCultoRepositoryInterface $participaciones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $bloqueCultoId = null): LengthAwarePaginator
    {
        return $this->participaciones->paginate($perPage, $bloqueCultoId);
    }

    public function create(array $data, int $actorId): ParticipacionCulto
    {
        $this->assertNoScheduleConflict((int) $data['bloque_culto_id'], (int) $data['miembro_id']);

        return $this->transactions->execute(function () use ($data, $actorId): ParticipacionCulto {
            $participacion = $this->participaciones->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'participaciones_culto', $participacion->id, null, $participacion->getAttributes());

            return $participacion;
        });
    }

    public function update(ParticipacionCulto $participacionCulto, array $data, int $actorId): ParticipacionCulto
    {
        $bloqueId = (int) ($data['bloque_culto_id'] ?? $participacionCulto->bloque_culto_id);
        $miembroId = (int) ($data['miembro_id'] ?? $participacionCulto->miembro_id);
        $this->assertNoScheduleConflict($bloqueId, $miembroId, $participacionCulto->id);

        return $this->transactions->execute(function () use ($participacionCulto, $data, $actorId): ParticipacionCulto {
            $before = $participacionCulto->getAttributes();
            $updated = $this->participaciones->update($participacionCulto, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'participaciones_culto', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(ParticipacionCulto $participacionCulto, int $actorId): void
    {
        $this->transactions->execute(function () use ($participacionCulto, $actorId): void {
            $before = $participacionCulto->getAttributes();
            $this->participaciones->delete($participacionCulto);
            $this->auditorias->record($actorId, 'DELETE', 'participaciones_culto', $participacionCulto->id, $before, null);
        });
    }

    private function assertNoScheduleConflict(int $bloqueCultoId, int $miembroId, ?int $excludeParticipacionId = null): void
    {
        $bloque = BloqueCulto::query()->with('culto')->findOrFail($bloqueCultoId);
        $culto = $bloque->culto;

        if ($this->participaciones->hasScheduleConflict($miembroId, $culto, $excludeParticipacionId)) {
            throw ValidationException::withMessages([
                'miembro_id' => 'El miembro tiene otra participación que se solapa con el horario de este culto.',
            ]);
        }
    }
}
