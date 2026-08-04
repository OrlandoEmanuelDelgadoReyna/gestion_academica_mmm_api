<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Iglesia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Iglesia */
final class IglesiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'codigo' => $this->codigo, 'nombre' => $this->nombre, 'direccion' => $this->direccion, 'telefono' => $this->telefono, 'correo_electronico' => $this->correo_electronico, 'activo' => $this->activo, 'miembros_count' => $this->whenCounted('miembros')];
    }
}
