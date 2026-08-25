<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Material;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Support\MaterialStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Throwable;

/** Transactional application service for learning material publication. */
final class MaterialService
{
    public function __construct(
        private MaterialRepositoryInterface $materiales,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        return $this->materiales->paginate($perPage, $programacionAcademicaId, $assignedMiembroId);
    }

    public function create(array $data, int $actor, ?UploadedFile $archivo = null): Material
    {
        $storedPath = null;

        try {
            return $this->transactions->execute(function () use ($data, $actor, $archivo, &$storedPath): Material {
                if ($archivo !== null) {
                    $storedPath = MaterialStorage::storeUpload($archivo);
                    $data['ruta_recurso'] = $storedPath;
                }

                $data['creado_por_usuario_id'] = $actor;
                $material = $this->materiales->create($data);
                $this->auditorias->record($actor, 'CREATE', 'materiales', $material->id, null, $material->getAttributes());

                return $material->load(['programacionAcademica.curso', 'tipoMaterial']);
            });
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($storedPath);
            throw $exception;
        }
    }

    public function update(Material $material, array $data, int $actor, ?UploadedFile $archivo = null): Material
    {
        $previousPath = $material->ruta_recurso;
        $storedPath = null;

        try {
            $updated = $this->transactions->execute(function () use ($material, $data, $actor, $archivo, &$storedPath): Material {
                if ($archivo !== null) {
                    $storedPath = MaterialStorage::storeUpload($archivo);
                    $data['ruta_recurso'] = $storedPath;
                }

                $before = $material->getAttributes();
                $updated = $this->materiales->update($material, $data);
                $this->auditorias->record($actor, 'UPDATE', 'materiales', $updated->id, $before, $updated->getAttributes());

                return $updated->load(['programacionAcademica.curso', 'tipoMaterial']);
            });
        } catch (Throwable $exception) {
            MaterialStorage::deleteManaged($storedPath);
            throw $exception;
        }

        if ($updated->ruta_recurso !== $previousPath) {
            MaterialStorage::deleteManaged($previousPath);
        }

        return $updated;
    }
}
