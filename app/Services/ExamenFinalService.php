<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\ExamenFinalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for final exam configuration. */
final class ExamenFinalService
{
    public function __construct(
        private ExamenFinalRepositoryInterface $examenes,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->examenes->paginate($perPage);
    }

    public function create(array $data, int $actor): ExamenFinal
    {
        return $this->transactions->execute(function () use ($data, $actor): ExamenFinal {
            if (ExamenFinal::query()->where('programacion_academica_id', $data['programacion_academica_id'])->exists()) {
                throw ValidationException::withMessages(['programacion_academica_id' => 'Ya existe un examen final para esta programación.']);
            }

            $examen = $this->examenes->create($data);
            $this->auditorias->record($actor, 'CREATE', 'examenes_finales', $examen->id, null, $examen->getAttributes());

            return $examen;
        });
    }

    public function update(ExamenFinal $examen, array $data, int $actor): ExamenFinal
    {
        return $this->transactions->execute(function () use ($examen, $data, $actor): ExamenFinal {
            $before = $examen->getAttributes();
            $updated = $this->examenes->update($examen, $data);
            $this->auditorias->record($actor, 'UPDATE', 'examenes_finales', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(ExamenFinal $examen, int $actor): void
    {
        $this->transactions->execute(function () use ($examen, $actor): void {
            $before = $examen->getAttributes();
            $this->examenes->delete($examen);
            $this->auditorias->record($actor, 'DELETE', 'examenes_finales', $examen->id, $before, null);
        });
    }
}
