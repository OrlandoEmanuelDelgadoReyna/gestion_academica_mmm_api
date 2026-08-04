<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CursoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Transactional application service for course catalog administration. */
final class CursoService
{
    public function __construct(
        private CursoRepositoryInterface $cursos,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->cursos->paginate($perPage);
    }

    public function create(array $data, int $actor): Curso
    {
        return $this->transactions->execute(function () use ($data, $actor): Curso {
            $curso = $this->cursos->create($data);
            $this->auditorias->record($actor, 'CREATE', 'cursos', $curso->id, null, $curso->getAttributes());

            return $curso;
        });
    }

    public function update(Curso $curso, array $data, int $actor): Curso
    {
        return $this->transactions->execute(function () use ($curso, $data, $actor): Curso {
            $before = $curso->getAttributes();
            $updated = $this->cursos->update($curso, $data);
            $this->auditorias->record($actor, 'UPDATE', 'cursos', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }
}
