<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProgramacionAcademica;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProgramacionAcademica */
final class ProgramacionAcademicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'curso_id' => $this->curso_id,
            'aula_id' => $this->aula_id,
            'periodo' => $this->periodo,
            'grupo' => $this->grupo,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
            'capacidad' => $this->capacidad,
            'escala_maxima' => $this->escala_maxima,
            'nota_minima_aprobatoria' => $this->nota_minima_aprobatoria,
            'maximo_intentos_examen' => $this->maximo_intentos_examen,
            'estado' => $this->estado,
            'curso' => new CursoResource($this->whenLoaded('curso')),
            'aula' => $this->whenLoaded('aula'),
            'docentes' => MiembroResource::collection($this->whenLoaded('docentes')),
            'estados_membresia_permitidos' => $this->whenLoaded('estadosMembresiaPermitidos'),
            'horarios' => ProgramacionHorarioResource::collection($this->whenLoaded('horarios')),
        ];
    }
}
