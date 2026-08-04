<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Usuario;
use App\Repositories\Contracts\UsuarioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentUsuarioRepository implements UsuarioRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Usuario::query()->with(['miembro.iglesia', 'roles'])->latest('id')->paginate($perPage);
    }

    public function findOrFail(int $id): Usuario
    {
        return Usuario::query()->with(['miembro.iglesia', 'roles'])->findOrFail($id);
    }

    public function create(array $attributes): Usuario
    {
        return Usuario::query()->create($attributes);
    }

    public function update(Usuario $usuario, array $attributes): Usuario
    {
        $usuario->update($attributes);

        return $usuario->refresh();
    }

    public function syncRoles(Usuario $usuario, array $roleIds): void
    {
        $usuario->roles()->sync($roleIds);
    }
}
