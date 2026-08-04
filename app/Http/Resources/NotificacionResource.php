<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Notificacion */
final class NotificacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'tipo' => $this->tipo,
            'enviado_at' => $this->enviado_at,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
            'destinatarios' => NotificacionDestinatarioResource::collection($this->whenLoaded('destinatarios')),
        ];
    }
}
