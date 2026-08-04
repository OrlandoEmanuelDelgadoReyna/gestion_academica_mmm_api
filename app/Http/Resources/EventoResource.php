<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Evento */
final class EventoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'inicio_at' => $this->inicio_at,
            'fin_at' => $this->fin_at,
            'lugar' => $this->lugar,
            'estado' => $this->estado,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
        ];
    }
}
