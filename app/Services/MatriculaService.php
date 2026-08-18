<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MatriculaHorarioConflictException;
use App\Models\HistorialMembresia;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\MatriculaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Applies enrolment eligibility and capacity rules atomically. */
final class MatriculaService
{
    public function __construct(
        private MatriculaRepositoryInterface $matriculas,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private HorarioConflictService $horarioConflict,
    ) {}

    public function paginate(int $perPage, ?int $programacionAcademicaId = null, ?string $estado = null, ?int $assignedMiembroId = null): LengthAwarePaginator
    {
        return $this->matriculas->paginate($perPage, $programacionAcademicaId, $estado, $assignedMiembroId);
    }

    public function create(ProgramacionAcademica $programacion, Miembro $miembro, int $actor): Matricula
    {
        return $this->transactions->execute(function () use ($programacion, $miembro, $actor): Matricula {
            $this->assertCanEnroll($programacion, $miembro);

            $matricula = $this->matriculas->create([
                'programacion_academica_id' => $programacion->id,
                'miembro_id' => $miembro->id,
                'fecha_matricula' => now(),
                'estado' => 'activa',
            ]);

            $this->auditorias->record($actor, 'CREATE', 'matriculas', $matricula->id, null, $matricula->getAttributes());

            return $matricula->load([
                'programacionAcademica.curso',
                'programacionAcademica.horarios',
                'programacionAcademica.docentes',
                'miembro',
            ]);
        });
    }

    public function update(Matricula $matricula, array $data, int $actor): Matricula
    {
        return $this->transactions->execute(function () use ($matricula, $data, $actor): Matricula {
            $before = $matricula->getAttributes();
            $updated = $this->matriculas->update($matricula, $data);
            $this->auditorias->record($actor, 'UPDATE', 'matriculas', $updated->id, $before, $updated->getAttributes());

            return $updated->load([
                'programacionAcademica.curso',
                'programacionAcademica.horarios',
                'programacionAcademica.docentes',
                'miembro',
            ]);
        });
    }

    public function updateEstado(Matricula $matricula, string $estado, int $actor): Matricula
    {
        if (! in_array($estado, ['activa', 'retirada', 'completada'], true)) {
            throw ValidationException::withMessages(['estado' => 'El estado de matrícula no es válido.']);
        }

        return $this->transactions->execute(function () use ($matricula, $estado, $actor): Matricula {
            $before = $matricula->getAttributes();
            $updated = $this->matriculas->updateEstado($matricula, $estado);
            $this->auditorias->record($actor, 'STATE_TRANSITION', 'matriculas', $updated->id, $before, $updated->getAttributes());

            return $updated->load([
                'programacionAcademica.curso',
                'programacionAcademica.horarios',
                'programacionAcademica.docentes',
                'miembro',
            ]);
        });
    }

    public function validateScheduleConflict(Miembro $miembro, ProgramacionAcademica $programacion): void
    {
        // Fuente principal: horarios recurrentes (programacion_horarios).
        // Política explícita: si la programación destino no tiene horarios, esta capa no bloquea
        // (mismo criterio que el check legacy cuando no hay sesiones).
        $horarios = $this->normalizeProgramacionHorarios($programacion);

        if ($horarios !== []) {
            $conflict = $this->horarioConflict->findMiembroConflict(
                $miembro->id,
                $horarios,
                $programacion->id,
            );

            if ($conflict !== null) {
                throw MatriculaHorarioConflictException::make(
                    $this->buildConflictoHorarioPayload($conflict),
                );
            }
        }

        // Segunda capa (temporal): sesiones concretas, si existen.
        if ($this->matriculas->hasScheduleConflict($miembro, $programacion)) {
            throw MatriculaHorarioConflictException::make(null);
        }
    }

    private function assertCanEnroll(ProgramacionAcademica $programacion, Miembro $miembro): void
    {
        if ($programacion->estado !== 'abierta') {
            throw ValidationException::withMessages(['programacion_academica_id' => 'La programación no admite matrículas.']);
        }

        if ($this->matriculas->countEnrollments($programacion) >= $programacion->capacidad) {
            throw ValidationException::withMessages(['programacion_academica_id' => 'No hay cupos disponibles.']);
        }

        if ($this->matriculas->existsForMember($programacion, $miembro)) {
            throw ValidationException::withMessages(['miembro_id' => 'El miembro ya está matriculado en esta programación.']);
        }

        $stateId = HistorialMembresia::query()
            ->where('miembro_id', $miembro->id)
            ->whereNull('fecha_fin')
            ->value('estado_membresia_id');

        if ($programacion->estadosMembresiaPermitidos()->exists()
            && ! $programacion->estadosMembresiaPermitidos()->whereKey($stateId)->exists()) {
            throw ValidationException::withMessages(['miembro_id' => 'El estado de membresía no es elegible.']);
        }

        $this->validateScheduleConflict($miembro, $programacion);
    }

    /**
     * @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>
     */
    private function normalizeProgramacionHorarios(ProgramacionAcademica $programacion): array
    {
        return $programacion->horarios()
            ->get(['dia_semana', 'hora_inicio', 'hora_fin'])
            ->map(fn ($slot): array => [
                'dia_semana' => (int) $slot->dia_semana,
                'hora_inicio' => $this->horarioConflict->normalizeTime((string) $slot->hora_inicio),
                'hora_fin' => $this->horarioConflict->normalizeTime((string) $slot->hora_fin),
            ])
            ->all();
    }

    /**
     * @param  array{programacion_academica_id: int, dia_semana: int, hora_inicio: string, hora_fin: string}  $conflict
     * @return array<string, mixed>|null
     */
    private function buildConflictoHorarioPayload(array $conflict): ?array
    {
        $programacion = ProgramacionAcademica::query()
            ->with('curso:id,nombre,codigo')
            ->find($conflict['programacion_academica_id']);

        if ($programacion === null) {
            return null;
        }

        $dias = [
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
            7 => 'Domingo',
        ];

        $cursoNombre = $programacion->curso?->nombre;
        if ($cursoNombre === null || $cursoNombre === '') {
            return null;
        }

        return [
            'programacion_id' => $programacion->id,
            'curso' => $cursoNombre,
            'curso_codigo' => $programacion->curso?->codigo,
            'grupo' => $programacion->grupo,
            'periodo' => $programacion->periodo,
            'dia_semana' => $conflict['dia_semana'],
            'dia' => $dias[$conflict['dia_semana']] ?? null,
            'hora_inicio' => $conflict['hora_inicio'],
            'hora_fin' => $conflict['hora_fin'],
        ];
    }
}
