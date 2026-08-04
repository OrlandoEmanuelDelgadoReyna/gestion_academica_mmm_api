<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\HistorialMembresia;
use App\Models\Miembro;
use App\Repositories\Contracts\MiembroRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentMiembroRepository implements MiembroRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Miembro::query()->with(['iglesia', 'usuario'])->orderBy('apellidos')->paginate($perPage);
    }

    public function create(array $attributes): Miembro
    {
        return Miembro::query()->create($attributes);
    }

    public function update(Miembro $miembro, array $attributes): Miembro
    {
        $miembro->update($attributes);

        return $miembro->refresh();
    }

    public function currentMembershipState(Miembro $miembro): ?int
    {
        return HistorialMembresia::query()->where('miembro_id', $miembro->id)->whereNull('fecha_fin')->value('estado_membresia_id');
    }

    public function transition(Miembro $miembro, int $stateId, string $date, ?string $observation, int $actorId): void
    {
        HistorialMembresia::query()->where('miembro_id', $miembro->id)->whereNull('fecha_fin')->update(['fecha_fin' => $date]);
        HistorialMembresia::query()->create(['miembro_id' => $miembro->id, 'estado_membresia_id' => $stateId, 'fecha_inicio' => $date, 'observacion' => $observation, 'registrado_por_usuario_id' => $actorId]);
    }
}
