<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
final class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'nombre_usuario' => $this->nombre_usuario, 'activo' => $this->activo, 'ultimo_acceso_at' => $this->ultimo_acceso_at?->toAtomString(), 'miembro' => ['id' => $this->miembro?->id, 'nombre_completo' => $this->miembro?->nombre_completo], 'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($rol) => ['id' => $rol->id, 'codigo' => $rol->codigo, 'nombre' => $rol->nombre]))];
    }
}
