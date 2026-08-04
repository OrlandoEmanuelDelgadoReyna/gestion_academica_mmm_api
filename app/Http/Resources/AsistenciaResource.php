<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Asistencia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Asistencia */
final class AsistenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sesion_id' => $this->sesion_id,
            'matricula_id' => $this->matricula_id,
            'estado' => $this->estado,
            'observacion' => $this->observacion,
            'registrado_por_usuario_id' => $this->registrado_por_usuario_id,
            'sesion' => new SesionResource($this->whenLoaded('sesion')),
            'matricula' => new MatriculaResource($this->whenLoaded('matricula')),
        ];
    }
}
