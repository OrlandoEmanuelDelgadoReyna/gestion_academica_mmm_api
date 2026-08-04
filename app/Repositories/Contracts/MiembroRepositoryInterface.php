<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Miembro;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MiembroRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Miembro;

    public function update(Miembro $miembro, array $attributes): Miembro;

    public function currentMembershipState(Miembro $miembro): ?int;

    public function transition(Miembro $miembro, int $stateId, string $date, ?string $observation, int $actorId): void;
}
