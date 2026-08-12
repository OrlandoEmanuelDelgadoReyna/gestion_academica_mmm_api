<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramacionAcademica;
use App\Models\ProgramacionHorario;

/** Detects overlapping recurring weekly schedules (docentes / future matrículas). */
final class HorarioConflictService
{
    /**
     * True when two half-open intervals [start, end) overlap.
     * Consecutive slots (19:00–21:00 and 21:00–23:00) do not conflict.
     */
    public function timesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $aStart = $this->toMinutes($startA);
        $aEnd = $this->toMinutes($endA);
        $bStart = $this->toMinutes($startB);
        $bEnd = $this->toMinutes($endB);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /**
     * @param  array{dia_semana: int, hora_inicio: string, hora_fin: string}  $a
     * @param  array{dia_semana: int, hora_inicio: string, hora_fin: string}  $b
     */
    public function schedulesOverlap(array $a, array $b): bool
    {
        if ((int) $a['dia_semana'] !== (int) $b['dia_semana']) {
            return false;
        }

        return $this->timesOverlap(
            (string) $a['hora_inicio'],
            (string) $a['hora_fin'],
            (string) $b['hora_inicio'],
            (string) $b['hora_fin'],
        );
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     */
    public function hasInternalOverlap(array $horarios): bool
    {
        $count = count($horarios);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->schedulesOverlap($horarios[$i], $horarios[$j])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     * @return array{dia_semana: int, hora_inicio: string, hora_fin: string}|null
     */
    public function findDocenteConflict(
        int $miembroId,
        array $horarios,
        ?int $excludeProgramacionId = null,
    ): ?array {
        if ($horarios === []) {
            return null;
        }

        $programacionIds = ProgramacionAcademica::query()
            ->whereHas('docentes', fn ($q) => $q->whereKey($miembroId))
            ->when(
                $excludeProgramacionId !== null,
                fn ($q) => $q->whereKeyNot($excludeProgramacionId),
            )
            ->whereNotIn('estado', ['cancelada'])
            ->pluck('id');

        if ($programacionIds->isEmpty()) {
            return null;
        }

        $existing = ProgramacionHorario::query()
            ->whereIn('programacion_academica_id', $programacionIds)
            ->get(['dia_semana', 'hora_inicio', 'hora_fin']);

        foreach ($existing as $slot) {
            $existingSlot = [
                'dia_semana' => (int) $slot->dia_semana,
                'hora_inicio' => $this->normalizeTime((string) $slot->hora_inicio),
                'hora_fin' => $this->normalizeTime((string) $slot->hora_fin),
            ];

            foreach ($horarios as $candidate) {
                if ($this->schedulesOverlap($existingSlot, $candidate)) {
                    return $existingSlot;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     */
    public function docenteHasConflict(
        int $miembroId,
        array $horarios,
        ?int $excludeProgramacionId = null,
    ): bool {
        return $this->findDocenteConflict($miembroId, $horarios, $excludeProgramacionId) !== null;
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     */
    public function miembroHasConflict(
        int $miembroId,
        array $horarios,
        ?int $excludeProgramacionId = null,
    ): bool {
        if ($horarios === []) {
            return false;
        }

        $programacionIds = ProgramacionAcademica::query()
            ->whereHas(
                'matriculas',
                fn ($q) => $q->where('miembro_id', $miembroId)->where('estado', 'activa'),
            )
            ->when(
                $excludeProgramacionId !== null,
                fn ($q) => $q->whereKeyNot($excludeProgramacionId),
            )
            ->whereNotIn('estado', ['cancelada'])
            ->pluck('id');

        if ($programacionIds->isEmpty()) {
            return false;
        }

        $existing = ProgramacionHorario::query()
            ->whereIn('programacion_academica_id', $programacionIds)
            ->get(['dia_semana', 'hora_inicio', 'hora_fin']);

        foreach ($existing as $slot) {
            $existingSlot = [
                'dia_semana' => (int) $slot->dia_semana,
                'hora_inicio' => $this->normalizeTime((string) $slot->hora_inicio),
                'hora_fin' => $this->normalizeTime((string) $slot->hora_fin),
            ];

            foreach ($horarios as $candidate) {
                if ($this->schedulesOverlap($existingSlot, $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function normalizeTime(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) !== 1) {
            return $value;
        }

        return substr($value, 0, 5);
    }

    public function toMinutes(string $time): int
    {
        $normalized = $this->normalizeTime($time);
        [$hours, $minutes] = array_map('intval', explode(':', $normalized));

        return ($hours * 60) + $minutes;
    }
}
