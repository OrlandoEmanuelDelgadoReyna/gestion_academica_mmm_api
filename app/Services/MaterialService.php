<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Material;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Transactional application service for learning material publication. */
final class MaterialService
{
    public function __construct(
        private MaterialRepositoryInterface $materiales,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->materiales->paginate($perPage);
    }

    public function create(array $data, int $actor, ?UploadedFile $archivo = null): Material
    {
        return $this->transactions->execute(function () use ($data, $actor, $archivo): Material {
            if ($archivo !== null) {
                $data['ruta_recurso'] = Storage::disk('local')->put('materiales', $archivo);
            }

            $data['creado_por_usuario_id'] = $actor;
            $material = $this->materiales->create($data);
            $this->auditorias->record($actor, 'CREATE', 'materiales', $material->id, null, $material->getAttributes());

            return $material->load(['programacionAcademica.curso', 'tipoMaterial']);
        });
    }

    public function update(Material $material, array $data, int $actor, ?UploadedFile $archivo = null): Material
    {
        return $this->transactions->execute(function () use ($material, $data, $actor, $archivo): Material {
            if ($archivo !== null) {
                $data['ruta_recurso'] = Storage::disk('local')->put('materiales', $archivo);
            }

            $before = $material->getAttributes();
            $updated = $this->materiales->update($material, $data);
            $this->auditorias->record($actor, 'UPDATE', 'materiales', $updated->id, $before, $updated->getAttributes());

            return $updated->load(['programacionAcademica.curso', 'tipoMaterial']);
        });
    }
}
