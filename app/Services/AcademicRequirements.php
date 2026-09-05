<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asistencia;
use App\Models\Matricula;
use App\Models\Sesion;

/** Shared academic thresholds and attendance used by grades, certificates and completion. */
final class AcademicRequirements
{
    public const float NOTA_MINIMA_APROBATORIA = 14.0;

    public const float ASISTENCIA_MINIMA = 80.0;

    /** @return array{porcentaje: ?float, cumple: bool, total_sesiones: int, presentes: int} */
    public function asistenciaDe(Matricula $matricula): array
    {
        $totalSesiones = Sesion::query()
            ->where('programacion_academica_id', $matricula->programacion_academica_id)
            ->count();

        if ($totalSesiones === 0) {
            return [
                'porcentaje' => null,
                'cumple' => false,
                'total_sesiones' => 0,
                'presentes' => 0,
            ];
        }

        $presentes = Asistencia::query()
            ->where('matricula_id', $matricula->id)
            ->whereIn('estado', ['asistio', 'justificado'])
            ->count();

        $porcentaje = round(($presentes / $totalSesiones) * 100, 2);

        return [
            'porcentaje' => $porcentaje,
            'cumple' => $porcentaje >= self::ASISTENCIA_MINIMA,
            'total_sesiones' => $totalSesiones,
            'presentes' => $presentes,
        ];
    }

    public function notaFinalAprueba(?float $notaFinal): bool
    {
        return $notaFinal !== null && $notaFinal >= self::NOTA_MINIMA_APROBATORIA;
    }
}
