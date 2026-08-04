<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\ProgramacionAcademicaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentProgramacionAcademicaRepository implements ProgramacionAcademicaRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return ProgramacionAcademica::query()
            ->with(['curso', 'aula'])
            ->orderByDesc('fecha_inicio')
            ->paginate($perPage);
    }

    public function create(array $data): ProgramacionAcademica
    {
        [$relations, $attributes] = $this->splitRelations($data);
        $programacion = ProgramacionAcademica::query()->create($attributes);
        $this->syncRelations($programacion, $relations);

        return $programacion->refresh()->load(['curso', 'aula']);
    }

    public function update(ProgramacionAcademica $programacion, array $data): ProgramacionAcademica
    {
        [$relations, $attributes] = $this->splitRelations($data);
        $programacion->update($attributes);
        $this->syncRelations($programacion, $relations);

        return $programacion->refresh()->load(['curso', 'aula']);
    }

    public function updateEstado(ProgramacionAcademica $programacion, string $estado): ProgramacionAcademica
    {
        $programacion->update(['estado' => $estado]);

        return $programacion->refresh();
    }

    /** @return array{0: array<string, array<int>>, 1: array<string, mixed>} */
    private function splitRelations(array $data): array
    {
        $relations = [
            'docente_ids' => $data['docente_ids'] ?? null,
            'estados_membresia_permitidos' => $data['estados_membresia_permitidos'] ?? null,
        ];
        unset($data['docente_ids'], $data['estados_membresia_permitidos']);

        return [$relations, $data];
    }

    /** @param array<string, array<int>|null> $relations */
    private function syncRelations(ProgramacionAcademica $programacion, array $relations): void
    {
        if ($relations['docente_ids'] !== null) {
            $programacion->docentes()->sync($relations['docente_ids']);
        }

        if ($relations['estados_membresia_permitidos'] !== null) {
            $programacion->estadosMembresiaPermitidos()->sync($relations['estados_membresia_permitidos']);
        }
    }
}
