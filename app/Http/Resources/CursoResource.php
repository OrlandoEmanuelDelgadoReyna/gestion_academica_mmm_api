<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Curso;
use App\Support\CursoPortadaStorage;
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
            'portada_path' => $this->portada_path,
            'portada_url' => CursoPortadaStorage::url($this->portada_path),
            'activo' => $this->activo,
            'programaciones_count' => (int) ($this->programaciones_count ?? 0),
            'matriculas_count' => (int) ($this->matriculas_count ?? 0),
            'iglesia' => new IglesiaResource($this->whenLoaded('iglesia')),
        ];
    }
}
