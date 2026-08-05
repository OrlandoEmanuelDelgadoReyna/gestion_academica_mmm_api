<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Miembro;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Miembro */
final class MiembroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iglesia_id' => $this->iglesia_id,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'nombre_completo' => $this->nombre_completo,
            'tipo_documento' => $this->tipo_documento,
            'numero_documento' => $this->numero_documento,
            'fecha_nacimiento' => $this->fecha_nacimiento?->format('Y-m-d'),
            'sexo' => $this->sexo,
            'correo_electronico' => $this->correo_electronico,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
        ];
    }
}
