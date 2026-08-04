<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BloqueCulto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BloqueCulto */
final class BloqueCultoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'culto_id' => $this->culto_id,
            'tipo_participacion_id' => $this->tipo_participacion_id,
            'orden' => $this->orden,
            'contenido' => $this->contenido,
            'culto' => new CultoResource($this->whenLoaded('culto')),
            'tipo_participacion' => $this->whenLoaded('tipoParticipacion'),
            'participaciones' => ParticipacionCultoResource::collection($this->whenLoaded('participaciones')),
        ];
    }
}
