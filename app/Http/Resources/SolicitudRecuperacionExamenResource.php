<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SolicitudRecuperacionExamen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SolicitudRecuperacionExamen */
final class SolicitudRecuperacionExamenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'examen_final_id' => $this->examen_final_id,
            'matricula_id' => $this->matricula_id,
            'estado' => $this->estado,
            'solicitada_at' => $this->solicitada_at?->toIso8601String(),
            'atendida_at' => $this->atendida_at?->toIso8601String(),
            'observacion' => $this->observacion,
            'alumno' => $this->when(
                $this->relationLoaded('matricula'),
                fn () => ['nombre_completo' => $this->matricula?->miembro?->nombre_completo],
            ),
        ];
    }
}
