<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\IntentoExamen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin IntentoExamen */
final class IntentoExamenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $completed = $this->estado === 'completado';

        return [
            'id' => $this->id,
            'examen_final_id' => $this->examen_final_id,
            'matricula_id' => $this->matricula_id,
            'inicio_at' => $this->inicio_at?->toIso8601String(),
            'fin_at' => $this->fin_at?->toIso8601String(),
            'estado' => $this->estado,
            'puntaje_obtenido' => $this->when($completed, $this->puntaje_obtenido),
            'examen_final' => new ExamenFinalResource($this->whenLoaded('examenFinal')),
            'respuestas' => $this->whenLoaded('respuestas', function () use ($completed) {
                return $this->respuestas->map(fn ($respuesta) => [
                    'id' => $respuesta->id,
                    'intento_examen_id' => $respuesta->intento_examen_id,
                    'pregunta_examen_id' => $respuesta->pregunta_examen_id,
                    'opcion_pregunta_id' => $respuesta->opcion_pregunta_id,
                    'respuesta_texto' => $respuesta->respuesta_texto,
                    'es_correcta' => $this->when($completed, $respuesta->es_correcta),
                    'puntaje_obtenido' => $this->when($completed, $respuesta->puntaje_obtenido),
                ])->values();
            }),
        ];
    }
}
