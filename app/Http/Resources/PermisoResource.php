<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Permiso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Permiso */
final class PermisoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'codigo' => $this->codigo, 'modulo' => $this->modulo, 'nombre' => $this->nombre, 'descripcion' => $this->descripcion, 'activo' => $this->activo];
    }
}
