<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ExamenFinal;
use App\Repositories\Contracts\ExamenFinalRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentExamenFinalRepository implements ExamenFinalRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginate(
        int $perPage,
        ?int $programacionAcademicaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator {
        $query = ExamenFinal::query()
            ->with([
                'programacionAcademica',
                'creadoPor.miembro',
                'notas.matricula.miembro',
                'solicitudesRecuperacion.matricula.miembro',
            ])
            ->withCount([
                'preguntas as preguntas_activas_count' => fn ($builder) => $builder->where('activo', true),
            ])
            ->when(
                $programacionAcademicaId !== null,
                fn ($builder) => $builder->where('programacion_academica_id', $programacionAcademicaId),
            );

        $this->academicAccess->constrainByAssignedProgramacion($query, $assignedMiembroId);
        $this->academicAccess->constrainByActiveEnrollment($query, $enrolledMiembroId);

        return $query->orderByDesc('created_at')->orderBy('id')->paginate($perPage);
    }

    public function create(array $data): ExamenFinal
    {
        return ExamenFinal::query()->create($data);
    }

    public function update(ExamenFinal $examen, array $data): ExamenFinal
    {
        $examen->update($data);

        return $examen->refresh();
    }

    public function delete(ExamenFinal $examen): void
    {
        $examen->delete();
    }
}
