<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CriterioEvaluacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CriterioEvaluacion */
final class CriterioEvaluacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'codigo' => $this->codigo,
            'origen' => $this->origen,
            'nombre' => $this->nombre,
            'porcentaje' => $this->porcentaje,
            'orden' => $this->orden,
            'programacion_academica' => $this->whenLoaded('programacionAcademica'),
        ];
    }
}
