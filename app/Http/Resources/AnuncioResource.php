<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Anuncio;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Anuncio */
final class AnuncioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'estado' => $this->estado,
            'publicado_at' => $this->publicado_at,
            'vence_at' => $this->vence_at,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
        ];
    }
}
