<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Rol;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\RolRepositoryInterface;

final class RolService
{
    public function __construct(private RolRepositoryInterface $roles, private DatabaseTransactionRepositoryInterface $transactions, private AuditoriaRepositoryInterface $auditorias) {}

    public function syncPermissions(Rol $rol, array $ids, int $actor): Rol
    {
        return $this->transactions->execute(function () use ($rol, $ids, $actor): Rol {
            $before = $rol->permisos()->pluck('id')->all();
            $this->roles->syncPermissions($rol, $ids);
            $this->auditorias->record($actor, 'SYNC_PERMISSIONS', 'roles', $rol->id, ['permisos' => $before], ['permisos' => $ids]);

            return $rol->load('permisos');
        });
    }

    public function create(array $data, int $actor): Rol
    {
        return $this->transactions->execute(function () use ($data, $actor): Rol {
            $permissions = $data['permisos'] ?? [];
            unset($data['permisos']);
            $rol = $this->roles->create($data);
            $this->roles->syncPermissions($rol, $permissions);
            $this->auditorias->record($actor, 'CREATE', 'roles', $rol->id, null, $rol->getAttributes());

            return $rol->load('permisos');
        });
    }

    public function update(Rol $rol, array $data, int $actor): Rol
    {
        return $this->transactions->execute(function () use ($rol, $data, $actor): Rol {
            $before = $rol->getAttributes();
            $updated = $this->roles->update($rol, $data);
            $this->auditorias->record($actor, 'UPDATE', 'roles', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }
}
