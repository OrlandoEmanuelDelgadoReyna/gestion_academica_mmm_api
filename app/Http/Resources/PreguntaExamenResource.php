<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PreguntaExamen;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PreguntaExamen */
final class PreguntaExamenResource extends JsonResource
{
    public bool $revealAnswers = false;

    public function toArray(Request $request): array
    {
        $reveal = $this->revealAnswers || (bool) ($this->additional['reveal'] ?? false);

        return [
            'id' => $this->id,
            'examen_final_id' => $this->examen_final_id,
            'orden' => $this->orden,
            'tipo' => $this->tipo,
            'enunciado' => $this->enunciado,
            'puntaje' => $this->puntaje,
            'activo' => $this->activo,
            'opciones' => $this->when(
                $this->relationLoaded('opciones'),
                fn () => $this->opciones->map(function ($opcion) use ($reveal): array {
                    $row = [
                        'id' => $opcion->id,
                        'pregunta_examen_id' => $opcion->pregunta_examen_id,
                        'orden' => $opcion->orden,
                        'texto' => $opcion->texto,
                    ];
                    if ($reveal) {
                        $row['es_correcta'] = (bool) $opcion->es_correcta;
                    }

                    return $row;
                })->values()->all(),
            ),
        ];
    }
}
