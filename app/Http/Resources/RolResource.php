<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Rol */
final class RolResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'codigo' => $this->codigo, 'nombre' => $this->nombre, 'descripcion' => $this->descripcion, 'activo' => $this->activo, 'permisos' => $this->whenLoaded('permisos', fn () => $this->permisos->map(fn ($p) => ['id' => $p->id, 'codigo' => $p->codigo]))];
    }
}
