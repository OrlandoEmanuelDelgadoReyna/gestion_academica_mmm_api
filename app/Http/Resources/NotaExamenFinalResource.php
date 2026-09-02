<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotaExamenFinal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NotaExamenFinal */
final class NotaExamenFinalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $examen = $this->relationLoaded('examenFinal') ? $this->examenFinal : null;
        $minima = $examen?->nota_minima_aprobatoria !== null ? (float) $examen->nota_minima_aprobatoria : null;
        $considerada = $this->notaConsiderada();

        return [
            'id' => $this->id,
            'examen_final_id' => $this->examen_final_id,
            'matricula_id' => $this->matricula_id,
            'alumno' => $this->when(
                $this->relationLoaded('matricula'),
                fn () => ['nombre_completo' => $this->matricula?->miembro?->nombre_completo],
            ),
            'nota' => $this->nota,
            'nota_recuperacion' => $this->nota_recuperacion,
            'nota_considerada' => $considerada,
            'resultado' => $this->resultado($minima),
            'puntos_faltantes' => $this->puntosFaltantes($minima),
            'origen' => $this->origen,
            'calificado_at' => $this->calificado_at?->toIso8601String(),
            'recuperacion_calificado_at' => $this->recuperacion_calificado_at?->toIso8601String(),
            'calificador' => $this->when(
                $this->relationLoaded('calificadoPor') && $this->calificadoPor !== null,
                fn () => [
                    'id' => $this->calificadoPor->id,
                    'nombre' => $this->calificadoPor->miembro?->nombre_completo
                        ?? $this->calificadoPor->nombre_usuario,
                ],
            ),
        ];
    }
}
