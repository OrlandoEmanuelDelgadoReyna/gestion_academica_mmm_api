<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Culto;
use App\Repositories\Contracts\CultoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCultoRepository implements CultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return Culto::query()
            ->with(['iglesia', 'tipoCulto'])
            ->when($iglesiaId, fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->orderByDesc('inicio_at')
            ->paginate($perPage);
    }

    public function create(array $data): Culto
    {
        return Culto::query()->create($data);
    }

    public function update(Culto $culto, array $data): Culto
    {
        $culto->update($data);

        return $culto->refresh();
    }

    public function delete(Culto $culto): void
    {
        $culto->delete();
    }
}
