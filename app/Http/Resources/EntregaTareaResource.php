<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EntregaTarea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin EntregaTarea */
final class EntregaTareaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tarea_id' => $this->tarea_id,
            'matricula_id' => $this->matricula_id,
            'alumno' => $this->when(
                $this->relationLoaded('matricula'),
                fn () => ['nombre_completo' => $this->matricula?->miembro?->nombre_completo],
            ),
            'contenido' => $this->contenido,
            'entregado_at' => $this->entregado_at?->toIso8601String(),
            'archivo' => [
                'disponible' => $this->hasArchivo(),
                'nombre_original' => $this->nombre_original,
                'mime' => $this->mime,
                'tamano_bytes' => $this->tamano_bytes,
            ],
            'nota' => $this->nota,
            'retroalimentacion' => $this->retroalimentacion,
            'calificado_at' => $this->calificado_at?->toIso8601String(),
            'calificador' => $this->when(
                $this->relationLoaded('calificadoPor') && $this->calificadoPor !== null,
                fn () => [
                    'id' => $this->calificadoPor->id,
                    'nombre' => $this->calificadoPor->miembro?->nombre_completo
                        ?? $this->calificadoPor->nombre_usuario,
                ],
            ),
            'tarea' => new TareaResource($this->whenLoaded('tarea')),
        ];
    }
}
