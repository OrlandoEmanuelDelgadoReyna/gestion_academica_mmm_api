<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Calificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Calificacion */
final class CalificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricula_id' => $this->matricula_id,
            'promedio_tareas' => $this->promedio_tareas,
            'nota_examen_final' => $this->nota_examen_final,
            'nota_final' => $this->nota_final,
            'estado' => $this->estado,
            'calculado_at' => $this->calculado_at,
            'matricula' => $this->whenLoaded('matricula'),
        ];
    }
}
