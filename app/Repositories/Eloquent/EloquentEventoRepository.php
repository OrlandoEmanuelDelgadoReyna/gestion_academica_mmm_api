<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Evento;
use App\Repositories\Contracts\EventoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentEventoRepository implements EventoRepositoryInterface
{
    public function paginate(int $perPage, ?int $iglesiaId = null): LengthAwarePaginator
    {
        return Evento::query()
            ->with('iglesia')
            ->when($iglesiaId, fn ($query) => $query->where('iglesia_id', $iglesiaId))
            ->orderByDesc('inicio_at')
            ->paginate($perPage);
    }

    public function create(array $data): Evento
    {
        return Evento::query()->create($data);
    }

    public function update(Evento $evento, array $data): Evento
    {
        $evento->update($data);

        return $evento->refresh();
    }

    public function delete(Evento $evento): void
    {
        $evento->delete();
    }
}
