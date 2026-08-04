<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Matricula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Matricula */
final class MatriculaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'miembro_id' => $this->miembro_id,
            'fecha_matricula' => $this->fecha_matricula?->toIso8601String(),
            'estado' => $this->estado,
            'programacion_academica' => new ProgramacionAcademicaResource($this->whenLoaded('programacionAcademica')),
            'miembro' => new MiembroResource($this->whenLoaded('miembro')),
        ];
    }
}
