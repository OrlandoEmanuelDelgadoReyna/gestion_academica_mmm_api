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
        return [
            'id' => $this->id,
            'examen_final_id' => $this->examen_final_id,
            'matricula_id' => $this->matricula_id,
            'inicio_at' => $this->inicio_at,
            'fin_at' => $this->fin_at,
            'estado' => $this->estado,
            'puntaje_obtenido' => $this->puntaje_obtenido,
            'examen_final' => new ExamenFinalResource($this->whenLoaded('examenFinal')),
            'respuestas' => RespuestaExamenResource::collection($this->whenLoaded('respuestas')),
        ];
    }
}
