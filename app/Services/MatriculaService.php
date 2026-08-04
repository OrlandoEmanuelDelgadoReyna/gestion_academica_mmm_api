<?php

declare(strict_types=1);

namespace App\Services;

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
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->matriculas->paginate($perPage);
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

            return $matricula->load(['programacionAcademica.curso', 'miembro']);
        });
    }

    public function update(Matricula $matricula, array $data, int $actor): Matricula
    {
        return $this->transactions->execute(function () use ($matricula, $data, $actor): Matricula {
            $before = $matricula->getAttributes();
            $updated = $this->matriculas->update($matricula, $data);
            $this->auditorias->record($actor, 'UPDATE', 'matriculas', $updated->id, $before, $updated->getAttributes());

            return $updated->load(['programacionAcademica.curso', 'miembro']);
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

            return $updated->load(['programacionAcademica.curso', 'miembro']);
        });
    }

    public function validateScheduleConflict(Miembro $miembro, ProgramacionAcademica $programacion): void
    {
        if ($this->matriculas->hasScheduleConflict($miembro, $programacion)) {
            throw ValidationException::withMessages(['miembro_id' => 'El miembro tiene un conflicto de horario con otra matrícula activa.']);
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
}
