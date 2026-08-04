<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ParticipacionCulto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ParticipacionCulto */
final class ParticipacionCultoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bloque_culto_id' => $this->bloque_culto_id,
            'miembro_id' => $this->miembro_id,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'bloque_culto' => new BloqueCultoResource($this->whenLoaded('bloqueCulto')),
            'miembro' => new MiembroResource($this->whenLoaded('miembro')),
        ];
    }
}
