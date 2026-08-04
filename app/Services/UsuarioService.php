<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\CrearUsuarioData;
use App\Models\Usuario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Coordinates transactional user-management use cases. */
final class UsuarioService
{
    public function __construct(private UsuarioRepositoryInterface $usuarios, private AuditoriaRepositoryInterface $auditorias, private DatabaseTransactionRepositoryInterface $transactions) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->usuarios->paginate($perPage);
    }

    public function find(int $id): Usuario
    {
        return $this->usuarios->findOrFail($id);
    }

    public function create(CrearUsuarioData $data, ?int $actorId): Usuario
    {
        return $this->transactions->execute(function () use ($data, $actorId): Usuario {
            $usuario = $this->usuarios->create(['miembro_id' => $data->miembroId, 'nombre_usuario' => $data->nombreUsuario, 'contrasena' => $data->contrasena, 'activo' => true]);
            $this->usuarios->syncRoles($usuario, $data->rolIds);
            $this->auditorias->record($actorId, 'crear', 'usuarios', $usuario->id, null, $usuario->only(['miembro_id', 'nombre_usuario', 'activo']));

            return $usuario->load('roles');
        });
    }

    public function deactivate(Usuario $usuario, ?int $actorId): Usuario
    {
        return $this->transactions->execute(function () use ($usuario, $actorId): Usuario {
            $before = $usuario->only('activo');
            $updated = $this->usuarios->update($usuario, ['activo' => false]);
            $this->auditorias->record($actorId, 'desactivar', 'usuarios', $updated->id, $before, $updated->only('activo'));

            return $updated;
        });
    }
}
