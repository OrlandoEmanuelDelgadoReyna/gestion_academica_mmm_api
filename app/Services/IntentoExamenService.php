<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\IntentoExamen;
use App\Models\Matricula;
use App\Models\PreguntaExamen;
use App\Models\RespuestaExamen;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\IntentoExamenRepositoryInterface;
use Illuminate\Validation\ValidationException;

/** Orchestrates exam attempts from start through graded submission. */
final class IntentoExamenService
{
    public function __construct(
        private IntentoExamenRepositoryInterface $intentos,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
    ) {}

    public function iniciar(int $examenFinalId, int $matriculaId, int $actor): IntentoExamen
    {
        return $this->transactions->execute(function () use ($examenFinalId, $matriculaId, $actor): IntentoExamen {
            $matricula = Matricula::query()->with('programacionAcademica')->findOrFail($matriculaId);

            if ($matricula->estado !== 'activa') {
                throw ValidationException::withMessages(['matricula_id' => 'La matrícula debe estar activa.']);
            }

            $examen = ExamenFinal::query()->findOrFail($examenFinalId);

            if ($examen->programacion_academica_id !== $matricula->programacion_academica_id) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen no pertenece a la programación de la matrícula.']);
            }

            if (! $examen->activo) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen final no está activo.']);
            }

            $now = now();

            if ($examen->inicio_at !== null && $now->lt($examen->inicio_at)) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen aún no está disponible.']);
            }

            if ($examen->fin_at !== null && $now->gt($examen->fin_at)) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen ya finalizó.']);
            }

            $maxIntentos = $matricula->programacionAcademica->maximo_intentos_examen;
            $intentosUsados = $this->intentos->countForMatricula($examenFinalId, $matriculaId);

            if ($intentosUsados >= $maxIntentos) {
                throw ValidationException::withMessages(['matricula_id' => 'Se alcanzó el máximo de intentos permitidos.']);
            }

            $intento = $this->intentos->create([
                'examen_final_id' => $examenFinalId,
                'matricula_id' => $matriculaId,
                'inicio_at' => $now,
                'estado' => 'en_progreso',
            ]);

            $this->auditorias->record($actor, 'CREATE', 'intentos_examen', $intento->id, null, $intento->getAttributes());

            return $intento;
        });
    }

    /** @param array<int, array<string, mixed>> $respuestas */
    public function enviar(IntentoExamen $intento, array $respuestas, int $actor): IntentoExamen
    {
        return $this->transactions->execute(function () use ($intento, $respuestas, $actor): IntentoExamen {
            if ($intento->estado !== 'en_progreso') {
                throw ValidationException::withMessages(['intento' => 'El intento no está en progreso.']);
            }

            $examen = $intento->examenFinal()->with('preguntas.opciones')->firstOrFail();
            $puntajeTotal = 0.0;

            foreach ($respuestas as $respuestaData) {
                $pregunta = $examen->preguntas->firstWhere('id', $respuestaData['pregunta_examen_id']);

                if ($pregunta === null) {
                    throw ValidationException::withMessages(['respuestas' => 'Pregunta no válida para este examen.']);
                }

                [$esCorrecta, $puntajeObtenido] = $this->gradeAnswer($pregunta, $respuestaData);
                $puntajeTotal += $puntajeObtenido;

                RespuestaExamen::query()->updateOrCreate(
                    ['intento_examen_id' => $intento->id, 'pregunta_examen_id' => $pregunta->id],
                    [
                        'opcion_pregunta_id' => $respuestaData['opcion_pregunta_id'] ?? null,
                        'respuesta_texto' => $respuestaData['respuesta_texto'] ?? null,
                        'es_correcta' => $esCorrecta,
                        'puntaje_obtenido' => $puntajeObtenido,
                    ],
                );
            }

            $before = $intento->getAttributes();
            $updated = $this->intentos->update($intento, [
                'fin_at' => now(),
                'estado' => 'completado',
                'puntaje_obtenido' => round($puntajeTotal, 2),
            ]);

            $this->auditorias->record($actor, 'UPDATE', 'intentos_examen', $updated->id, $before, $updated->getAttributes());

            return $updated->load('respuestas');
        });
    }

    /** @param array<string, mixed> $respuestaData */
    private function gradeAnswer(PreguntaExamen $pregunta, array $respuestaData): array
    {
        if ($pregunta->tipo === 'seleccion_unica') {
            $opcionId = $respuestaData['opcion_pregunta_id'] ?? null;

            if ($opcionId === null) {
                throw ValidationException::withMessages(['respuestas' => 'Debe seleccionar una opción.']);
            }

            $opcion = $pregunta->opciones->firstWhere('id', $opcionId);

            if ($opcion === null) {
                throw ValidationException::withMessages(['respuestas' => 'Opción no válida para la pregunta.']);
            }

            $esCorrecta = (bool) $opcion->es_correcta;

            return [$esCorrecta, $esCorrecta ? (float) $pregunta->puntaje : 0.0];
        }

        return [null, 0.0];
    }
}
