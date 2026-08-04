<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permiso;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\PermisoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PermisoService
{
    public function __construct(private PermisoRepositoryInterface $permisos, private DatabaseTransactionRepositoryInterface $transactions, private AuditoriaRepositoryInterface $auditorias) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->permisos->paginate($perPage);
    }

    public function create(array $data, int $actorId): Permiso
    {
        return $this->transactions->execute(function () use ($data, $actorId): Permiso {
            $permiso = $this->permisos->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'permisos', $permiso->id, null, $permiso->getAttributes());

            return $permiso;
        });
    }

    public function update(Permiso $permiso, array $data, int $actorId): Permiso
    {
        return $this->transactions->execute(function () use ($permiso, $data, $actorId): Permiso {
            $before = $permiso->getAttributes();
            $updated = $this->permisos->update($permiso, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'permisos', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }
}
