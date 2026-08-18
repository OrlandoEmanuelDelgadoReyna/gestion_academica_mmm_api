<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\ProgramacionAcademicaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for academic program scheduling. */
final class ProgramacionAcademicaService
{
    public function __construct(
        private ProgramacionAcademicaRepositoryInterface $programaciones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private HorarioConflictService $horarioConflict,
    ) {}

    public function paginate(int $perPage, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        return $this->programaciones->paginate($perPage, $assignedMiembroId);
    }

    public function create(array $data, int $actor): ProgramacionAcademica
    {
        $data['estado'] ??= 'borrador';
        $this->validateBusinessRules($data);
        $this->validateDocenteScheduleConflicts($data);

        return $this->transactions->execute(function () use ($data, $actor): ProgramacionAcademica {
            $programacion = $this->programaciones->create($data);
            $this->auditorias->record($actor, 'CREATE', 'programaciones_academicas', $programacion->id, null, $programacion->getAttributes());

            return $programacion;
        });
    }

    public function update(ProgramacionAcademica $programacion, array $data, int $actor): ProgramacionAcademica
    {
        $this->validateBusinessRules(array_merge($programacion->getAttributes(), $data));
        $this->validateDocenteScheduleConflicts($data, $programacion);

        return $this->transactions->execute(function () use ($programacion, $data, $actor): ProgramacionAcademica {
            $before = $programacion->getAttributes();
            $updated = $this->programaciones->update($programacion, $data);
            $this->auditorias->record($actor, 'UPDATE', 'programaciones_academicas', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    public function transitionEstado(ProgramacionAcademica $programacion, string $estado, int $actor): ProgramacionAcademica
    {
        return $this->transactions->execute(function () use ($programacion, $estado, $actor): ProgramacionAcademica {
            $before = $programacion->getAttributes();
            $updated = $this->programaciones->updateEstado($programacion, $estado);
            $this->auditorias->record($actor, 'STATE_TRANSITION', 'programaciones_academicas', $updated->id, $before, $updated->getAttributes());

            return $updated;
        });
    }

    private function validateBusinessRules(array $data): void
    {
        if (isset($data['fecha_inicio'], $data['fecha_fin']) && $data['fecha_fin'] < $data['fecha_inicio']) {
            throw ValidationException::withMessages(['fecha_fin' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.']);
        }

        if (isset($data['capacidad']) && (int) $data['capacidad'] <= 0) {
            throw ValidationException::withMessages(['capacidad' => 'La capacidad debe ser mayor que cero.']);
        }

        if (isset($data['nota_minima_aprobatoria'], $data['escala_maxima']) && (float) $data['nota_minima_aprobatoria'] > (float) $data['escala_maxima']) {
            throw ValidationException::withMessages(['nota_minima_aprobatoria' => 'La nota mínima no puede superar la escala máxima.']);
        }
    }

    private function validateDocenteScheduleConflicts(array $data, ?ProgramacionAcademica $existing = null): void
    {
        $horarios = $this->resolveHorariosForConflictCheck($data, $existing);
        if ($horarios === []) {
            return;
        }

        $docenteIds = $this->resolveDocenteIdsForConflictCheck($data, $existing);
        if ($docenteIds === []) {
            return;
        }

        foreach ($docenteIds as $miembroId) {
            $conflictSlot = $this->horarioConflict->findDocenteConflict(
                $miembroId,
                $horarios,
                $existing?->id,
            );

            if ($conflictSlot !== null) {
                throw ValidationException::withMessages([
                    'horarios' => $this->formatDocenteConflictMessage($conflictSlot),
                    'docente_ids' => $this->formatDocenteConflictMessage($conflictSlot),
                ]);
            }
        }
    }

    /** @param array{dia_semana: int, hora_inicio: string, hora_fin: string} $slot */
    private function formatDocenteConflictMessage(array $slot): string
    {
        $dias = [
            1 => 'lunes',
            2 => 'martes',
            3 => 'miércoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sábado',
            7 => 'domingo',
        ];

        $dia = $dias[$slot['dia_semana']] ?? 'día '.$slot['dia_semana'];
        $inicio = $slot['hora_inicio'];
        $fin = $slot['hora_fin'];

        return "El docente tiene un cruce de horarios con otra programación. Ya tiene una programación los {$dia} de {$inicio} a {$fin}.";
    }

    /**
     * @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>
     */
    private function resolveHorariosForConflictCheck(array $data, ?ProgramacionAcademica $existing): array
    {
        if (array_key_exists('horarios', $data) && is_array($data['horarios'])) {
            return $this->normalizeHorariosPayload($data['horarios']);
        }

        if ($existing === null) {
            return [];
        }

        return $existing->horarios()
            ->get(['dia_semana', 'hora_inicio', 'hora_fin'])
            ->map(fn ($slot): array => [
                'dia_semana' => (int) $slot->dia_semana,
                'hora_inicio' => $this->horarioConflict->normalizeTime((string) $slot->hora_inicio),
                'hora_fin' => $this->horarioConflict->normalizeTime((string) $slot->hora_fin),
            ])
            ->all();
    }

    /**
     * @return list<int>
     */
    private function resolveDocenteIdsForConflictCheck(array $data, ?ProgramacionAcademica $existing): array
    {
        if (array_key_exists('docente_ids', $data) && is_array($data['docente_ids'])) {
            return array_map('intval', $data['docente_ids']);
        }

        if ($existing === null) {
            return [];
        }

        return $existing->docentes()->pluck('miembros.id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<array<string, mixed>>  $horarios
     * @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>
     */
    private function normalizeHorariosPayload(array $horarios): array
    {
        $normalized = [];
        foreach ($horarios as $slot) {
            if (! is_array($slot) || ! isset($slot['dia_semana'], $slot['hora_inicio'], $slot['hora_fin'])) {
                continue;
            }
            $normalized[] = [
                'dia_semana' => (int) $slot['dia_semana'],
                'hora_inicio' => $this->horarioConflict->normalizeTime((string) $slot['hora_inicio']),
                'hora_fin' => $this->horarioConflict->normalizeTime((string) $slot['hora_fin']),
            ];
        }

        return $normalized;
    }
}
