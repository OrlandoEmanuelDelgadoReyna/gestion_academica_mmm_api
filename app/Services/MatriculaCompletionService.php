<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Matricula;
use App\Models\ProgramacionAcademica;
use Illuminate\Validation\ValidationException;

/** Marks enrollments completed when a closed program meets academic and attendance rules. */
final class MatriculaCompletionService
{
    public function __construct(
        private AcademicRequirements $requirements,
        private MatriculaService $matriculas,
    ) {}

    public function tryComplete(Matricula $matricula, int $actor): void
    {
        $matricula->loadMissing('programacionAcademica');

        if ($matricula->estado !== 'activa') {
            return;
        }

        if ($matricula->programacionAcademica?->estado !== 'cerrada') {
            return;
        }

        $calificacion = Calificacion::query()->where('matricula_id', $matricula->id)->first();
        $notaFinal = $calificacion?->nota_final !== null ? (float) $calificacion->nota_final : null;
        $asistencia = $this->requirements->asistenciaDe($matricula);

        if ($this->requirements->notaFinalAprueba($notaFinal) && $asistencia['cumple']) {
            $this->matriculas->completeAutomatically($matricula, $actor);
        }
    }

    public function recalcularYCompletar(Matricula $matricula, int $actor): void
    {
        try {
            app(CalificacionService::class)->calcular($matricula, $actor);
        } catch (ValidationException) {
            $this->tryComplete($matricula, $actor);
        }
    }

    public function syncProgramacion(ProgramacionAcademica $programacion, int $actor): void
    {
        if ($programacion->estado !== 'cerrada') {
            return;
        }

        $programacion->loadMissing('matriculas');

        foreach ($programacion->matriculas as $matricula) {
            if ($matricula->estado !== 'activa') {
                continue;
            }

            $this->recalcularYCompletar($matricula, $actor);
        }
    }
}
