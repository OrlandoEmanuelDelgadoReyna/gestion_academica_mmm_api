<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificacionDestinatario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Inbox row for the authenticated recipient only. */
/** @mixin NotificacionDestinatario */
final class NotificacionInboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $notificacion = $this->notificacion;

        return [
            'id' => $notificacion?->id,
            'titulo' => $notificacion?->titulo,
            'contenido' => $notificacion?->contenido,
            'tipo' => $notificacion?->tipo,
            'enviado_at' => $notificacion?->enviado_at,
            'estado' => $this->estado,
            'leido_at' => $this->leido_at,
        ];
    }
}
