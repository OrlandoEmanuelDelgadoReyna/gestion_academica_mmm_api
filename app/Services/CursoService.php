<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Curso;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CursoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Support\CursoPortadaStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Throwable;

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

    public function create(array $data, int $actor, ?UploadedFile $portada = null): Curso
    {
        $storedPath = null;

        try {
            return $this->transactions->execute(function () use ($data, $actor, $portada, &$storedPath): Curso {
                if ($portada !== null) {
                    $storedPath = CursoPortadaStorage::storeUpload($portada);
                    $data['portada_path'] = $storedPath;
                }

                unset($data['portada']);
                $curso = $this->cursos->create($data);
                $this->auditorias->record($actor, 'CREATE', 'cursos', $curso->id, null, $curso->getAttributes());

                return $curso;
            });
        } catch (Throwable $exception) {
            CursoPortadaStorage::deleteManaged($storedPath);
            throw $exception;
        }
    }

    public function update(Curso $curso, array $data, int $actor, ?UploadedFile $portada = null): Curso
    {
        $previousPath = $curso->portada_path;
        $storedPath = null;

        try {
            $updated = $this->transactions->execute(function () use ($curso, $data, $actor, $portada, &$storedPath): Curso {
                if ($portada !== null) {
                    $storedPath = CursoPortadaStorage::storeUpload($portada);
                    $data['portada_path'] = $storedPath;
                }

                unset($data['portada']);
                $before = $curso->getAttributes();
                $updated = $this->cursos->update($curso, $data);
                $this->auditorias->record($actor, 'UPDATE', 'cursos', $updated->id, $before, $updated->getAttributes());

                return $updated;
            });
        } catch (Throwable $exception) {
            CursoPortadaStorage::deleteManaged($storedPath);
            throw $exception;
        }

        if ($updated->portada_path !== $previousPath) {
            CursoPortadaStorage::deleteManaged($previousPath);
        }

        return $updated;
    }
}
