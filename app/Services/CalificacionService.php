<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Calificacion;
use App\Models\EntregaTarea;
use App\Models\ExamenFinal;
use App\Models\IntentoExamen;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\Tarea;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\CalificacionRepositoryInterface;
use App\Repositories\Contracts\CriterioEvaluacionRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Validation\ValidationException;

/** Computes consolidated grades from weighted evaluation criteria. */
final class CalificacionService
{
    public function __construct(
        private CalificacionRepositoryInterface $calificaciones,
        private CriterioEvaluacionRepositoryInterface $criterios,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function calcular(Matricula $matricula, int $actor): Calificacion
    {
        $calificacion = $this->transactions->execute(function () use ($matricula, $actor): Calificacion {
            $matricula->load('programacionAcademica');
            $programacion = $matricula->programacionAcademica;
            $criterios = $this->criterios->forProgramacion($programacion->id);

            if ($criterios->isEmpty()) {
                throw ValidationException::withMessages(['matricula_id' => 'No hay criterios de evaluación configurados.']);
            }

            if (abs($criterios->sum('porcentaje') - 100) > 0.01) {
                throw ValidationException::withMessages(['matricula_id' => 'Los criterios de evaluación deben sumar 100%.']);
            }

            $escalaMaxima = (float) $programacion->escala_maxima;
            $promedioTareas = $this->calcularPromedioTareas($matricula, $escalaMaxima);
            $notaExamen = $this->calcularNotaExamen($matricula, $escalaMaxima);

            $notaFinal = 0.0;

            foreach ($criterios as $criterio) {
                $componente = match ($criterio->origen) {
                    'tareas' => $promedioTareas,
                    'examen_final' => $notaExamen,
                    default => throw ValidationException::withMessages(['matricula_id' => 'Origen de criterio no soportado.']),
                };

                if ($componente === null) {
                    throw ValidationException::withMessages(['matricula_id' => "Falta calificación para el criterio {$criterio->nombre}."]);
                }

                $notaFinal += $componente * ((float) $criterio->porcentaje / 100);
            }

            $notaFinal = round($notaFinal, 2);
            $estado = $notaFinal >= AcademicRequirements::NOTA_MINIMA_APROBATORIA ? 'aprobada' : 'desaprobada';

            $before = $this->calificaciones->findByMatricula($matricula->id)?->getAttributes();
            $calificacion = $this->calificaciones->upsertForMatricula($matricula->id, [
                'promedio_tareas' => $promedioTareas,
                'nota_examen_final' => $notaExamen,
                'nota_final' => $notaFinal,
                'estado' => $estado,
                'calculado_at' => now(),
            ]);

            $this->auditorias->record(
                $actor,
                $before === null ? 'CREATE' : 'UPDATE',
                'calificaciones',
                $calificacion->id,
                $before,
                $calificacion->getAttributes(),
            );

            return $calificacion;
        });

        app(MatriculaCompletionService::class)->tryComplete($matricula, $actor);

        return $calificacion;
    }

    private function calcularPromedioTareas(Matricula $matricula, float $escalaMaxima): ?float
    {
        $tareas = Tarea::query()
            ->where('programacion_academica_id', $matricula->programacion_academica_id)
            ->get();

        if ($tareas->isEmpty()) {
            return null;
        }

        $notasNormalizadas = [];

        foreach ($tareas as $tarea) {
            $entrega = EntregaTarea::query()
                ->where('tarea_id', $tarea->id)
                ->where('matricula_id', $matricula->id)
                ->whereNotNull('nota')
                ->first();

            if ($entrega === null) {
                return null;
            }

            $puntajeMaximo = (float) $tarea->puntaje_maximo;

            if ($puntajeMaximo <= 0) {
                continue;
            }

            $notasNormalizadas[] = ((float) $entrega->nota / $puntajeMaximo) * $escalaMaxima;
        }

        if ($notasNormalizadas === []) {
            return null;
        }

        return round(array_sum($notasNormalizadas) / count($notasNormalizadas), 2);
    }

    private function calcularNotaExamen(Matricula $matricula, float $escalaMaxima): ?float
    {
        $examen = ExamenFinal::query()
            ->where('programacion_academica_id', $matricula->programacion_academica_id)
            ->first();

        if ($examen === null) {
            return $this->notaDesdeMejorIntento($matricula, $escalaMaxima);
        }

        $registro = NotaExamenFinal::query()
            ->where('examen_final_id', $examen->id)
            ->where('matricula_id', $matricula->id)
            ->first();

        $nota = $registro?->notaConsiderada();
        if ($nota !== null) {
            $puntajeMaximo = (float) $examen->puntaje_maximo;
            if ($puntajeMaximo <= 0) {
                return null;
            }

            return round(($nota / $puntajeMaximo) * $escalaMaxima, 2);
        }

        return $this->notaDesdeMejorIntento($matricula, $escalaMaxima);
    }

    private function notaDesdeMejorIntento(Matricula $matricula, float $escalaMaxima): ?float
    {
        $intento = IntentoExamen::query()
            ->where('matricula_id', $matricula->id)
            ->where('estado', 'completado')
            ->whereNotNull('puntaje_obtenido')
            ->with('examenFinal')
            ->orderByDesc('puntaje_obtenido')
            ->first();

        if ($intento === null || $intento->examenFinal === null) {
            return null;
        }

        $puntajeMaximo = (float) $intento->examenFinal->puntaje_maximo;

        if ($puntajeMaximo <= 0) {
            return null;
        }

        return round(((float) $intento->puntaje_obtenido / $puntajeMaximo) * $escalaMaxima, 2);
    }
}
