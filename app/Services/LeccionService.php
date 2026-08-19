<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Leccion;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\LeccionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Transactional application service for course lesson catalog administration. */
final class LeccionService
{
    public function __construct(
        private LeccionRepositoryInterface $lecciones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, int $cursoId): LengthAwarePaginator
    {
        return $this->lecciones->paginate($perPage, $cursoId);
    }

    public function create(array $data, int $actor): Leccion
    {
        return $this->transactions->execute(function () use ($data, $actor): Leccion {
            $leccion = $this->lecciones->create($data);
            $this->auditorias->record($actor, 'CREATE', 'lecciones', $leccion->id, null, $leccion->getAttributes());

            return $leccion;
        });
    }

    public function update(Leccion $leccion, array $data, int $actor): Leccion
    {
        return $this->transactions->execute(function () use ($leccion, $data, $actor): Leccion {
            $before = $leccion->getAttributes();
            $updated = $this->lecciones->update($leccion, $data);
            $this->auditorias->record($actor, 'UPDATE', 'lecciones', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }
}
