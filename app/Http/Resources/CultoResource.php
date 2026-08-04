<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Culto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Culto */
final class CultoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'tipo_culto_id' => $this->tipo_culto_id,
            'inicio_at' => $this->inicio_at,
            'fin_at' => $this->fin_at,
            'lugar' => $this->lugar,
            'estado' => $this->estado,
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
            'tipo_culto' => $this->whenLoaded('tipoCulto'),
            'bloques' => BloqueCultoResource::collection($this->whenLoaded('bloques')),
        ];
    }
}
