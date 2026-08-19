<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sesion */
final class SesionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'orden' => $this->orden,
            'inicio_at' => $this->inicio_at?->toIso8601String(),
            'fin_at' => $this->fin_at?->toIso8601String(),
            'tema' => $this->tema,
            'estado' => $this->estado,
            'programacion_academica' => new ProgramacionAcademicaResource($this->whenLoaded('programacionAcademica')),
            'lecciones' => $this->whenLoaded(
                'lecciones',
                fn () => LeccionResource::collection($this->lecciones),
            ),
        ];
    }
}
