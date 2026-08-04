<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tarea;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\TareaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Transactional application service for assignment administration. */
final class TareaService
{
    public function __construct(
        private TareaRepositoryInterface $tareas,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->tareas->paginate($perPage);
    }

    public function create(array $data, int $actor): Tarea
    {
        return $this->transactions->execute(function () use ($data, $actor): Tarea {
            $tarea = $this->tareas->create($data);
            $this->auditorias->record($actor, 'CREATE', 'tareas', $tarea->id, null, $tarea->getAttributes());

            return $tarea;
        });
    }

    public function update(Tarea $tarea, array $data, int $actor): Tarea
    {
        return $this->transactions->execute(function () use ($tarea, $data, $actor): Tarea {
            $before = $tarea->getAttributes();
            $updated = $this->tareas->update($tarea, $data);
            $this->auditorias->record($actor, 'UPDATE', 'tareas', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(Tarea $tarea, int $actor): void
    {
        $this->transactions->execute(function () use ($tarea, $actor): void {
            $before = $tarea->getAttributes();
            $this->tareas->delete($tarea);
            $this->auditorias->record($actor, 'DELETE', 'tareas', $tarea->id, $before, null);
        });
    }
}
