<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BloqueCulto;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\BloqueCultoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class BloqueCultoService
{
    public function __construct(
        private BloqueCultoRepositoryInterface $bloques,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $cultoId = null): LengthAwarePaginator
    {
        return $this->bloques->paginate($perPage, $cultoId);
    }

    public function create(array $data, int $actorId): BloqueCulto
    {
        return $this->transactions->execute(function () use ($data, $actorId): BloqueCulto {
            $bloque = $this->bloques->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'bloques_culto', $bloque->id, null, $bloque->getAttributes());

            return $bloque;
        });
    }

    public function update(BloqueCulto $bloqueCulto, array $data, int $actorId): BloqueCulto
    {
        return $this->transactions->execute(function () use ($bloqueCulto, $data, $actorId): BloqueCulto {
            $before = $bloqueCulto->getAttributes();
            $updated = $this->bloques->update($bloqueCulto, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'bloques_culto', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(BloqueCulto $bloqueCulto, int $actorId): void
    {
        $this->transactions->execute(function () use ($bloqueCulto, $actorId): void {
            $before = $bloqueCulto->getAttributes();
            $this->bloques->delete($bloqueCulto);
            $this->auditorias->record($actorId, 'DELETE', 'bloques_culto', $bloqueCulto->id, $before, null);
        });
    }
}
