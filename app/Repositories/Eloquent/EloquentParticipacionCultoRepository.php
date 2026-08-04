<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Culto;
use App\Models\ParticipacionCulto;
use App\Repositories\Contracts\ParticipacionCultoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentParticipacionCultoRepository implements ParticipacionCultoRepositoryInterface
{
    public function paginate(int $perPage, ?int $bloqueCultoId = null): LengthAwarePaginator
    {
        return ParticipacionCulto::query()
            ->with(['bloqueCulto.culto', 'miembro'])
            ->when($bloqueCultoId, fn ($query) => $query->where('bloque_culto_id', $bloqueCultoId))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function create(array $data): ParticipacionCulto
    {
        return ParticipacionCulto::query()->create($data);
    }

    public function update(ParticipacionCulto $participacionCulto, array $data): ParticipacionCulto
    {
        $participacionCulto->update($data);

        return $participacionCulto->refresh();
    }

    public function delete(ParticipacionCulto $participacionCulto): void
    {
        $participacionCulto->delete();
    }

    public function hasScheduleConflict(int $miembroId, Culto $culto, ?int $excludeParticipacionId = null): bool
    {
        return ParticipacionCulto::query()
            ->where('miembro_id', $miembroId)
            ->when($excludeParticipacionId, fn ($query) => $query->where('id', '!=', $excludeParticipacionId))
            ->whereHas('bloqueCulto.culto', function ($query) use ($culto): void {
                $query->where('id', '!=', $culto->id)
                    ->where('inicio_at', '<', $culto->fin_at)
                    ->where('fin_at', '>', $culto->inicio_at);
            })
            ->exists();
    }
}
