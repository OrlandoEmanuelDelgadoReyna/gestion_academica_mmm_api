<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\BloqueCulto;
use App\Repositories\Contracts\BloqueCultoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentBloqueCultoRepository implements BloqueCultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $cultoId = null): LengthAwarePaginator
    {
        return BloqueCulto::query()
            ->with(['culto', 'tipoParticipacion'])
            ->when($cultoId, fn ($query) => $query->where('culto_id', $cultoId))
            ->orderBy('culto_id')
            ->orderBy('orden')
            ->paginate($perPage);
    }

    public function create(array $data): BloqueCulto
    {
        return BloqueCulto::query()->create($data);
    }

    public function update(BloqueCulto $bloqueCulto, array $data): BloqueCulto
    {
        $bloqueCulto->update($data);

        return $bloqueCulto->refresh();
    }

    public function delete(BloqueCulto $bloqueCulto): void
    {
        $bloqueCulto->delete();
    }
}
