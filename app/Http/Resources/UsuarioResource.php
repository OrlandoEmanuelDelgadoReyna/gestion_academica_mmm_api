<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Usuario;
use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Usuario */
final class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_usuario' => $this->nombre_usuario,
            'activo' => $this->activo,
            'profile_photo_path' => $this->profile_photo_path,
            'profile_photo_url' => UsuarioProfilePhotoStorage::url($this->profile_photo_path),
            'ultimo_acceso_at' => $this->ultimo_acceso_at?->toAtomString(),
            'miembro' => [
                'id' => $this->miembro?->id,
                'iglesia_id' => $this->miembro?->iglesia_id !== null
                    ? (int) $this->miembro->iglesia_id
                    : null,
                'nombre_completo' => $this->miembro?->nombre_completo,
            ],
            'roles' => $this->whenLoaded(
                'roles',
                fn () => $this->roles->map(fn ($rol) => [
                    'id' => $rol->id,
                    'codigo' => $rol->codigo,
                    'nombre' => $rol->nombre,
                ]),
            ),
        ];
    }
}
