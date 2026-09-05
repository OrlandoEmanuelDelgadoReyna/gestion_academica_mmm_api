<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamenFinal;
use App\Models\NotaExamenFinal;
use App\Models\SolicitudRecuperacionExamen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExamenFinal */
final class ExamenFinalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $minima = $this->nota_minima_aprobatoria !== null ? (float) $this->nota_minima_aprobatoria : null;
        $miNota = $this->when(
            $this->relationLoaded('notas') && $request->user()?->miembro_id,
            function () use ($request, $minima) {
                $nota = $this->notas->first(function (NotaExamenFinal $nota) use ($request) {
                    return (int) $nota->matricula?->miembro_id === (int) $request->user()->miembro_id;
                });

                return $nota === null ? null : $this->resultadoPayload($nota, $minima);
            },
        );

        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'inicio_at' => $this->inicio_at?->toIso8601String(),
            'fin_at' => $this->fin_at?->toIso8601String(),
            'puntaje_maximo' => $this->puntaje_maximo,
            'nota_minima_aprobatoria' => $this->nota_minima_aprobatoria,
            'activo' => $this->activo,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'recuperacion_titulo' => $this->recuperacion_titulo,
            'recuperacion_descripcion' => $this->recuperacion_descripcion,
            'recuperacion_at' => $this->recuperacion_at?->toIso8601String(),
            'recuperacion_generada_at' => $this->recuperacion_generada_at?->toIso8601String(),
            'tiene_recuperacion' => $this->tieneRecuperacionGenerada(),
            'tiene_preguntas' => $this->when(
                $this->relationLoaded('preguntas') || isset($this->preguntas_activas_count),
                function () {
                    if ($this->relationLoaded('preguntas')) {
                        return $this->preguntas->where('activo', true)->isNotEmpty();
                    }

                    return (int) $this->preguntas_activas_count > 0;
                },
            ),
            'programacion_academica' => $this->whenLoaded('programacionAcademica'),
            'preguntas' => $this->whenLoaded('preguntas', function () use ($request) {
                $reveal = $request->user()?->can('manageQuestions', $this->resource) ?? false;
                $items = $reveal
                    ? $this->preguntas
                    : $this->preguntas->where('activo', true)->values();

                return $items->map(function ($pregunta) use ($reveal) {
                    $resource = new PreguntaExamenResource($pregunta);
                    $resource->revealAnswers = $reveal;

                    return $resource;
                });
            }),
            'mi_resultado' => $miNota,
            'mi_solicitud' => $this->when(
                $this->relationLoaded('solicitudesRecuperacion') && $request->user()?->miembro_id,
                function () use ($request) {
                    $solicitud = $this->solicitudesRecuperacion
                        ->sortByDesc('id')
                        ->first(fn (SolicitudRecuperacionExamen $item) => (int) $item->matricula?->miembro_id === (int) $request->user()->miembro_id);

                    return $solicitud === null ? null : new SolicitudRecuperacionExamenResource($solicitud);
                },
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function resultadoPayload(NotaExamenFinal $nota, ?float $minima): array
    {
        $considerada = $nota->notaConsiderada();
        $resultado = $nota->resultado($minima);
        $faltan = $nota->puntosFaltantes($minima);

        return [
            'nota' => $nota->nota,
            'nota_recuperacion' => $nota->nota_recuperacion,
            'nota_considerada' => $considerada,
            'resultado' => $resultado,
            'puntos_faltantes' => $faltan,
            'calificado_at' => $nota->calificado_at?->toIso8601String(),
        ];
    }
}
