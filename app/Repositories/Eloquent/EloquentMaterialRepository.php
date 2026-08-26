<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Material;
use App\Repositories\Contracts\MaterialRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentMaterialRepository implements MaterialRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?int $assignedMiembroId = null, ?int $enrolledMiembroId = null): LengthAwarePaginator
    {
        $query = Material::query()
            ->with(['programacionAcademica.curso', 'tipoMaterial'])
            ->when(
                $programacionAcademicaId !== null,
                fn ($builder) => $builder->where('programacion_academica_id', $programacionAcademicaId),
            );

        $this->academicAccess->constrainByAssignedProgramacion($query, $assignedMiembroId);
        $this->academicAccess->constrainByActiveEnrollment($query, $enrolledMiembroId);

        return $query
            ->orderByDesc('publicado_at')
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function create(array $data): Material
    {
        return Material::query()->create($data);
    }

    public function update(Material $material, array $data): Material
    {
        $material->update($data);

        return $material->refresh();
    }
}
