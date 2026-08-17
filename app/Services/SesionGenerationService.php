<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProgramacionAcademica;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\SesionRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/** Expands programacion_horarios into concrete Sesion rows for a date range. */
final class SesionGenerationService
{
    public function __construct(
        private SesionRepositoryInterface $sesiones,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private HorarioConflictService $horarioConflict,
    ) {}

    public function generate(ProgramacionAcademica $programacion, int $actor): SesionGenerationResult
    {
        return $this->transactions->execute(function () use ($programacion, $actor): SesionGenerationResult {
            $locked = ProgramacionAcademica::query()
                ->whereKey($programacion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $horarios = $this->validateAndNormalizeHorarios($locked);
            $candidates = $this->expandCandidates($locked, $horarios);
            $existing = $this->sesiones->lockByProgramacion($locked);
            $existingKeys = $this->existingSlotKeys($existing);

            $nextOrden = (int) ($existing->max('orden') ?? 0);
            $created = [];
            $skipped = 0;

            foreach ($candidates as $candidate) {
                $key = $this->slotKey($candidate['inicio_at'], $candidate['fin_at']);
                if (isset($existingKeys[$key])) {
                    $skipped++;
                    continue;
                }

                $nextOrden++;
                $sesion = $this->sesiones->create([
                    'programacion_academica_id' => $locked->id,
                    'orden' => $nextOrden,
                    'inicio_at' => $candidate['inicio_at']->format('Y-m-d H:i:s'),
                    'fin_at' => $candidate['fin_at']->format('Y-m-d H:i:s'),
                    'tema' => null,
                    'estado' => 'programada',
                ]);
                $this->auditorias->record($actor, 'CREATE', 'sesiones', $sesion->id, null, $sesion->getAttributes());
                $created[] = $sesion;
                $existingKeys[$key] = true;
            }

            return new SesionGenerationResult(
                created: count($created),
                skipped: $skipped,
                total: count($created) + $skipped,
                createdSesiones: $created,
            );
        });
    }

    /**
     * @return list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>
     */
    private function validateAndNormalizeHorarios(ProgramacionAcademica $programacion): array
    {
        if ($programacion->fecha_inicio === null || $programacion->fecha_fin === null) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'La programación debe tener fecha de inicio y fecha de fin.',
            ]);
        }

        if ($programacion->fecha_fin->lt($programacion->fecha_inicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio.',
            ]);
        }

        $slots = $programacion->horarios()
            ->get(['dia_semana', 'hora_inicio', 'hora_fin']);

        if ($slots->isEmpty()) {
            throw ValidationException::withMessages([
                'horarios' => 'La programación no tiene horarios para generar sesiones.',
            ]);
        }

        $normalized = [];
        foreach ($slots as $index => $slot) {
            $inicio = $this->horarioConflict->normalizeTime((string) $slot->hora_inicio);
            $fin = $this->horarioConflict->normalizeTime((string) $slot->hora_fin);

            if (! $this->isValidClock($inicio) || ! $this->isValidClock($fin)) {
                throw ValidationException::withMessages([
                    "horarios.$index.hora_inicio" => 'El formato de hora debe ser HH:MM.',
                ]);
            }

            if ($this->horarioConflict->toMinutes($inicio) >= $this->horarioConflict->toMinutes($fin)) {
                throw ValidationException::withMessages([
                    "horarios.$index.hora_fin" => 'La hora de fin debe ser posterior a la hora de inicio.',
                ]);
            }

            $normalized[] = [
                'dia_semana' => (int) $slot->dia_semana,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{dia_semana: int, hora_inicio: string, hora_fin: string}>  $horarios
     * @return list<array{inicio_at: CarbonInterface, fin_at: CarbonInterface}>
     */
    private function expandCandidates(ProgramacionAcademica $programacion, array $horarios): array
    {
        $candidates = [];
        $cursor = $programacion->fecha_inicio->copy()->startOfDay();
        $last = $programacion->fecha_fin->copy()->startOfDay();

        while ($cursor->lte($last)) {
            $diaSemana = $cursor->isoWeekday();
            foreach ($horarios as $slot) {
                if ($slot['dia_semana'] !== $diaSemana) {
                    continue;
                }

                $inicio = $cursor->copy()->setTimeFromTimeString($slot['hora_inicio']);
                $fin = $cursor->copy()->setTimeFromTimeString($slot['hora_fin']);
                $candidates[] = [
                    'inicio_at' => $inicio,
                    'fin_at' => $fin,
                ];
            }
            $cursor->addDay();
        }

        usort(
            $candidates,
            static function (array $a, array $b): int {
                $byStart = $a['inicio_at']->timestamp <=> $b['inicio_at']->timestamp;
                if ($byStart !== 0) {
                    return $byStart;
                }

                return $a['fin_at']->timestamp <=> $b['fin_at']->timestamp;
            },
        );

        return $candidates;
    }

    /** @param \Illuminate\Support\Collection<int, Sesion> $existing */
    private function existingSlotKeys($existing): array
    {
        $keys = [];
        foreach ($existing as $sesion) {
            if ($sesion->inicio_at === null || $sesion->fin_at === null) {
                continue;
            }
            $keys[$this->slotKey($sesion->inicio_at, $sesion->fin_at)] = true;
        }

        return $keys;
    }

    private function slotKey(CarbonInterface $inicio, CarbonInterface $fin): string
    {
        return $inicio->format('Y-m-d H:i').'|'.$fin->format('Y-m-d H:i');
    }

    private function isValidClock(string $time): bool
    {
        if (preg_match('/^(\d{2}):(\d{2})$/', $time, $matches) !== 1) {
            return false;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
    }
}
