<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\IntentoExamen;
use App\Models\Matricula;
use App\Models\PreguntaExamen;
use App\Models\RespuestaExamen;
use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;
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
        private NotaExamenFinalService $notas,
    ) {}

    public function iniciar(int $examenFinalId, Usuario $actor, ?int $requestedMatriculaId = null): IntentoExamen
    {
        return $this->transactions->execute(function () use ($examenFinalId, $actor, $requestedMatriculaId): IntentoExamen {
            $examen = ExamenFinal::query()->findOrFail($examenFinalId);
            $matricula = $this->resolveOwnedActiveMatricula($actor, $examen, $requestedMatriculaId);
            $matricula->load('programacionAcademica');

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

            $abierto = IntentoExamen::query()
                ->where('examen_final_id', $examen->id)
                ->where('matricula_id', $matricula->id)
                ->where('estado', 'en_progreso')
                ->first();

            if ($abierto !== null) {
                throw ValidationException::withMessages(['intento' => 'Ya tienes un intento en progreso.']);
            }

            $maxIntentos = $matricula->programacionAcademica->maximo_intentos_examen;
            $intentosUsados = $this->intentos->countForMatricula($examen->id, $matricula->id);
            $recuperacionAprobada = $this->notas->solicitudActiva($examen, $matricula)?->estado === SolicitudRecuperacionExamen::APROBADA;

            if ($intentosUsados >= $maxIntentos && ! $recuperacionAprobada) {
                throw ValidationException::withMessages(['matricula_id' => 'Se alcanzó el máximo de intentos permitidos.']);
            }

            $intento = $this->intentos->create([
                'examen_final_id' => $examen->id,
                'matricula_id' => $matricula->id,
                'inicio_at' => $now,
                'estado' => 'en_progreso',
            ]);

            $this->auditorias->record($actor->id, 'CREATE', 'intentos_examen', $intento->id, null, $intento->getAttributes());

            return $intento->load('examenFinal');
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
            $now = now();
            if (! $examen->activo) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen final no está activo.']);
            }
            if ($examen->inicio_at !== null && $now->lt($examen->inicio_at)) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen aún no está disponible.']);
            }
            if ($examen->fin_at !== null && $now->gt($examen->fin_at)) {
                throw ValidationException::withMessages(['examen_final_id' => 'El examen ya finalizó.']);
            }

            $puntajeTotal = 0.0;

            foreach ($respuestas as $respuestaData) {
                unset($respuestaData['es_correcta'], $respuestaData['puntaje_obtenido'], $respuestaData['aprobado']);
                $pregunta = $examen->preguntas->firstWhere('id', $respuestaData['pregunta_examen_id']);

                if ($pregunta === null || $pregunta->activo === false) {
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

            $matricula = $intento->matricula()->firstOrFail();
            $solicitud = $this->notas->solicitudActiva($examen, $matricula);
            $esRecuperacion = $solicitud?->estado === SolicitudRecuperacionExamen::APROBADA;
            $this->notas->upsertDesdeIntento($examen, $matricula, (float) $updated->puntaje_obtenido, $actor, $esRecuperacion);
            if ($esRecuperacion && $solicitud !== null) {
                $solicitud->update(['estado' => SolicitudRecuperacionExamen::REALIZADA]);
            }

            return $updated->load(['respuestas', 'examenFinal']);
        });
    }

    private function resolveOwnedActiveMatricula(Usuario $actor, ExamenFinal $examen, ?int $requestedMatriculaId): Matricula
    {
        if ($actor->miembro_id === null) {
            abort(403);
        }

        $matricula = Matricula::query()
            ->where('miembro_id', $actor->miembro_id)
            ->where('programacion_academica_id', $examen->programacion_academica_id)
            ->where('estado', 'activa')
            ->first();

        if ($matricula === null) {
            abort(403);
        }

        if ($requestedMatriculaId !== null && $requestedMatriculaId !== (int) $matricula->id) {
            abort(403);
        }

        return $matricula;
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
