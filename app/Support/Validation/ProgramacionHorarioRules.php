<?php

declare(strict_types=1);

namespace App\Support\Validation;

use App\Services\HorarioConflictService;
use Illuminate\Validation\Validator;

/** Shared validation helpers for recurring programacion horarios. */
final class ProgramacionHorarioRules
{
    /** @return array<string, list<string>> */
    public static function storeRules(): array
    {
        return [
            'horarios' => ['required', 'array', 'min:1'],
            ...self::itemRules(),
        ];
    }

    /** @return array<string, list<string>> */
    public static function updateRules(): array
    {
        return [
            'horarios' => ['sometimes', 'array', 'min:1'],
            ...self::itemRules(),
        ];
    }

    /** @return array<string, list<string>> */
    private static function itemRules(): array
    {
        return [
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7'],
            'horarios.*.hora_inicio' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'horarios.*.hora_fin' => ['required', 'string', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
        ];
    }

    public static function after(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $horarios = $validator->getData()['horarios'] ?? null;
            if (! is_array($horarios)) {
                return;
            }

            $conflict = app(HorarioConflictService::class);
            $seen = [];

            foreach ($horarios as $index => $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                $dia = isset($slot['dia_semana']) ? (int) $slot['dia_semana'] : null;
                $inicio = isset($slot['hora_inicio']) ? $conflict->normalizeTime((string) $slot['hora_inicio']) : null;
                $fin = isset($slot['hora_fin']) ? $conflict->normalizeTime((string) $slot['hora_fin']) : null;

                if ($inicio === null || $fin === null || $dia === null) {
                    continue;
                }

                if (! self::isValidClock($inicio) || ! self::isValidClock($fin)) {
                    $validator->errors()->add("horarios.$index.hora_inicio", 'El formato de hora debe ser HH:MM.');
                    continue;
                }

                if ($conflict->toMinutes($inicio) >= $conflict->toMinutes($fin)) {
                    $validator->errors()->add(
                        "horarios.$index.hora_fin",
                        'La hora de fin debe ser posterior a la hora de inicio.',
                    );
                }

                $key = $dia.'|'.$inicio.'|'.$fin;
                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "horarios.$index",
                        'No se permiten horarios duplicados (mismo día y mismo rango).',
                    );
                }
                $seen[$key] = true;
            }

            $normalized = [];
            foreach ($horarios as $slot) {
                if (! is_array($slot) || ! isset($slot['dia_semana'], $slot['hora_inicio'], $slot['hora_fin'])) {
                    continue;
                }
                $normalized[] = [
                    'dia_semana' => (int) $slot['dia_semana'],
                    'hora_inicio' => $conflict->normalizeTime((string) $slot['hora_inicio']),
                    'hora_fin' => $conflict->normalizeTime((string) $slot['hora_fin']),
                ];
            }

            if ($conflict->hasInternalOverlap($normalized)) {
                $validator->errors()->add(
                    'horarios',
                    'Los horarios de la misma programación no pueden solaparse el mismo día.',
                );
            }
        });
    }

    private static function isValidClock(string $time): bool
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $time, $m) !== 1) {
            return false;
        }

        $h = (int) $m[1];
        $min = (int) $m[2];

        return $h >= 0 && $h <= 23 && $min >= 0 && $min <= 59;
    }
}
