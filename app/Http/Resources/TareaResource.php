<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tarea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Tarea */
final class TareaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'publicado_at' => $this->publicado_at,
            'fecha_limite_at' => $this->fecha_limite_at,
            'puntaje_maximo' => $this->puntaje_maximo,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'programacion_academica' => $this->whenLoaded('programacionAcademica'),
            'creado_por' => new UsuarioResource($this->whenLoaded('creadoPor')),
        ];
    }
}
