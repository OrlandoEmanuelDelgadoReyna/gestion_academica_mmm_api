<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RespuestaExamen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RespuestaExamen */
final class RespuestaExamenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'intento_examen_id' => $this->intento_examen_id,
            'pregunta_examen_id' => $this->pregunta_examen_id,
            'opcion_pregunta_id' => $this->opcion_pregunta_id,
            'respuesta_texto' => $this->respuesta_texto,
            'es_correcta' => $this->es_correcta,
            'puntaje_obtenido' => $this->puntaje_obtenido,
        ];
    }
}
