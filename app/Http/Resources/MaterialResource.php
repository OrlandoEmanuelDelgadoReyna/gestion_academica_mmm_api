<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Material;
use App\Support\MaterialStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Material */
final class MaterialResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $esUrl = MaterialStorage::isExternalUrl($this->ruta_recurso);
        $recursoPrivado = MaterialStorage::isManagedPath($this->ruta_recurso);

        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'tipo_material_id' => $this->tipo_material_id,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'ruta_recurso' => $esUrl ? $this->ruta_recurso : null,
            'es_url' => $esUrl,
            'recurso_privado' => $recursoPrivado,
            'descargable' => $recursoPrivado && MaterialStorage::exists($this->ruta_recurso),
            'tamano_bytes' => $recursoPrivado ? MaterialStorage::sizeBytes($this->ruta_recurso) : null,
            'publicado_at' => $this->publicado_at?->toIso8601String(),
            'creado_por_usuario_id' => $this->creado_por_usuario_id,
            'programacion_academica' => new ProgramacionAcademicaResource($this->whenLoaded('programacionAcademica')),
            'tipo_material' => new TipoMaterialResource($this->whenLoaded('tipoMaterial')),
        ];
    }
}
