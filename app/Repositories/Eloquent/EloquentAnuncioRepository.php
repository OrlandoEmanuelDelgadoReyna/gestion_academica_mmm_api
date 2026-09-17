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

    public function paginatePublicados(int $perPage, int $iglesiaId, ?array $audiencias = null): LengthAwarePaginator
    {
        return Anuncio::query()
            ->with(['iglesia', 'creadoPor'])
            ->where('iglesia_id', $iglesiaId)
            ->vigente()
            ->when($audiencias !== null, function ($query) use ($audiencias): void {
                $query->where(function ($inner) use ($audiencias): void {
                    $inner->whereIn('audiencia', $audiencias);
                    if (in_array(Anuncio::AUDIENCIA_TODOS, $audiencias, true)) {
                        $inner->orWhereNull('audiencia');
                    }
                });
            })
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
