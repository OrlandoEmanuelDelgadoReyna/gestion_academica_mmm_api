<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificacionDestinatario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotificacionDestinatario */
final class NotificacionDestinatarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'notificacion_id' => $this->notificacion_id,
            'usuario_id' => $this->usuario_id,
            'estado' => $this->estado,
            'entregado_at' => $this->entregado_at,
            'leido_at' => $this->leido_at,
            'usuario' => new UsuarioResource($this->whenLoaded('usuario')),
        ];
    }
}
