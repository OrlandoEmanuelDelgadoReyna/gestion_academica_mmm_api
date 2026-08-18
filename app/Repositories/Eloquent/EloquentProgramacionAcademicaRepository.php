<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\ProgramacionAcademicaRepositoryInterface;
use App\Services\AcademicAccess;
use App\Services\HorarioConflictService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentProgramacionAcademicaRepository implements ProgramacionAcademicaRepositoryInterface
{
    public function __construct(
        private HorarioConflictService $horarioConflict,
        private AcademicAccess $academicAccess,
    ) {}

    public function paginate(int $perPage, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        $query = ProgramacionAcademica::query()
            ->with(['curso', 'aula', 'horarios']);

        $this->academicAccess->constrainProgramaciones($query, $assignedMiembroId);

        return $query
            ->orderByDesc('fecha_inicio')
            ->paginate($perPage);
    }

    public function create(array $data): ProgramacionAcademica
    {
        [$relations, $attributes] = $this->splitRelations($data);
        $programacion = ProgramacionAcademica::query()->create($attributes);
        $this->syncRelations($programacion, $relations);

        return $programacion->refresh()->load(['curso', 'aula', 'docentes', 'horarios']);
    }

    public function update(ProgramacionAcademica $programacion, array $data): ProgramacionAcademica
    {
        [$relations, $attributes] = $this->splitRelations($data);
        if ($attributes !== []) {
            $programacion->update($attributes);
        }
        $this->syncRelations($programacion, $relations);

        return $programacion->refresh()->load(['curso', 'aula', 'docentes', 'horarios']);
    }

    public function updateEstado(ProgramacionAcademica $programacion, string $estado): ProgramacionAcademica
    {
        $programacion->update(['estado' => $estado]);

        return $programacion->refresh()->load(['curso', 'aula', 'docentes', 'horarios']);
    }

    /**
     * @return array{0: array{docente_ids: array<int>|null, estados_membresia_permitidos: array<int>|null, horarios: list<array<string, mixed>>|null}, 1: array<string, mixed>}
     */
    private function splitRelations(array $data): array
    {
        $relations = [
            'docente_ids' => $data['docente_ids'] ?? null,
            'estados_membresia_permitidos' => $data['estados_membresia_permitidos'] ?? null,
            'horarios' => array_key_exists('horarios', $data) ? $data['horarios'] : null,
        ];
        unset($data['docente_ids'], $data['estados_membresia_permitidos'], $data['horarios']);

        return [$relations, $data];
    }

    /** @param array{docente_ids: array<int>|null, estados_membresia_permitidos: array<int>|null, horarios: list<array<string, mixed>>|null} $relations */
    private function syncRelations(ProgramacionAcademica $programacion, array $relations): void
    {
        if ($relations['docente_ids'] !== null) {
            $programacion->docentes()->sync($relations['docente_ids']);
        }

        if ($relations['estados_membresia_permitidos'] !== null) {
            $programacion->estadosMembresiaPermitidos()->sync($relations['estados_membresia_permitidos']);
        }

        if ($relations['horarios'] !== null) {
            $this->replaceHorarios($programacion, $relations['horarios']);
        }
    }

    /** @param list<array<string, mixed>> $horarios */
    private function replaceHorarios(ProgramacionAcademica $programacion, array $horarios): void
    {
        $programacion->horarios()->delete();

        foreach ($horarios as $slot) {
            $programacion->horarios()->create([
                'dia_semana' => (int) $slot['dia_semana'],
                'hora_inicio' => $this->toDbTime((string) $slot['hora_inicio']),
                'hora_fin' => $this->toDbTime((string) $slot['hora_fin']),
            ]);
        }
    }

    private function toDbTime(string $value): string
    {
        $normalized = $this->horarioConflict->normalizeTime($value);

        return strlen($normalized) === 5 ? $normalized.':00' : $normalized;
    }
}
