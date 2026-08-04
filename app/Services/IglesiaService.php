<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Iglesia;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\IglesiaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Transactional application service for church administration. */
final class IglesiaService
{
    public function __construct(private IglesiaRepositoryInterface $iglesias, private AuditoriaRepositoryInterface $auditorias, private DatabaseTransactionRepositoryInterface $transactions) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->iglesias->paginate($perPage);
    }

    public function create(array $attributes, int $actorId): Iglesia
    {
        return $this->transactions->execute(function () use ($attributes, $actorId): Iglesia {
            $iglesia = $this->iglesias->create($attributes);
            $this->auditorias->record($actorId, 'CREATE', 'iglesias', $iglesia->id, null, $iglesia->getAttributes());

            return $iglesia;
        });
    }

    public function update(Iglesia $iglesia, array $attributes, int $actorId): Iglesia
    {
        return $this->transactions->execute(function () use ($iglesia, $attributes, $actorId): Iglesia {
            $before = $iglesia->getAttributes();
            $updated = $this->iglesias->update($iglesia, $attributes);
            $this->auditorias->record($actorId, 'UPDATE', 'iglesias', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }
}
