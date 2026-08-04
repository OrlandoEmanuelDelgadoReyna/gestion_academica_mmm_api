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
            'contenido' => $this->contenido,
            'ruta_archivo' => $this->ruta_archivo,
            'entregado_at' => $this->entregado_at,
            'nota' => $this->nota,
            'retroalimentacion' => $this->retroalimentacion,
            'calificado_at' => $this->calificado_at,
            'calificado_por_usuario_id' => $this->calificado_por_usuario_id,
            'tarea' => new TareaResource($this->whenLoaded('tarea')),
            'matricula' => $this->whenLoaded('matricula'),
        ];
    }
}
