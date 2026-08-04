<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Curso */
final class CursoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
        ];
    }
}
