<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Culto;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CultoRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class CultoService
{
    public function __construct(
        private CultoRepositoryInterface $cultos,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return $this->cultos->paginate($perPage, $iglesiaId);
    }

    public function create(array $data, int $actorId): Culto
    {
        $data['creado_por_usuario_id'] = $actorId;

        return $this->transactions->execute(function () use ($data, $actorId): Culto {
            $culto = $this->cultos->create($data);
            $this->auditorias->record($actorId, 'CREATE', 'cultos', $culto->id, null, $culto->getAttributes());

            return $culto;
        });
    }

    public function update(Culto $culto, array $data, int $actorId): Culto
    {
        return $this->transactions->execute(function () use ($culto, $data, $actorId): Culto {
            $before = $culto->getAttributes();
            $updated = $this->cultos->update($culto, $data);
            $this->auditorias->record($actorId, 'UPDATE', 'cultos', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function delete(Culto $culto, int $actorId): void
    {
        $this->transactions->execute(function () use ($culto, $actorId): void {
            $before = $culto->getAttributes();
            $this->cultos->delete($culto);
            $this->auditorias->record($actorId, 'DELETE', 'cultos', $culto->id, $before, null);
        });
    }
}
