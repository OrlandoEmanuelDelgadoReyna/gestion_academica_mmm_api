<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Anuncio;
use App\Repositories\Contracts\AnuncioRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentAnuncioRepository implements AnuncioRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return Anuncio::query()
            ->with('iglesia')
            ->when($iglesiaId, fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->orderByDesc('publicado_at')
            ->paginate($perPage);
    }

    public function create(array $data): Anuncio
    {
        return Anuncio::query()->create($data);
    }

    public function update(Anuncio $anuncio, array $data): Anuncio
    {
        $anuncio->update($data);

        return $anuncio->refresh();
    }

    public function delete(Anuncio $anuncio): void
    {
        $anuncio->delete();
    }
}
