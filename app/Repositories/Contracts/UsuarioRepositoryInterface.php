<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UsuarioRepositoryInterface
{
    public function paginate(int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(int $id): Usuario;

    public function create(array $attributes): Usuario;

    public function update(Usuario $usuario, array $attributes): Usuario;

    /** @param list<int> $roleIds */
    public function syncRoles(Usuario $usuario, array $roleIds): void;
}
