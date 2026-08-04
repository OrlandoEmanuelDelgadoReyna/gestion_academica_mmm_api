<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ExamenFinal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExamenFinal */
final class ExamenFinalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'inicio_at' => $this->inicio_at,
            'fin_at' => $this->fin_at,
            'puntaje_maximo' => $this->puntaje_maximo,
            'nota_minima_aprobatoria' => $this->nota_minima_aprobatoria,
            'activo' => $this->activo,
            'programacion_academica' => $this->whenLoaded('programacionAcademica'),
        ];
    }
}
