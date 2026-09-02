<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\SolicitudRecuperacionExamen;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Registers and re-grades exam scores without mixing interactive attempts. */
final class NotaExamenFinalService
{
    public function __construct(
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private ExamenNotificacionDispatcher $notificaciones,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function roster(ExamenFinal $examen): Collection
    {
        $examen->loadMissing('programacionAcademica.matriculas.miembro');
        $notas = NotaExamenFinal::query()
            ->where('examen_final_id', $examen->id)
            ->with(['calificadoPor.miembro', 'matricula.miembro'])
            ->get()
            ->keyBy('matricula_id');
        $solicitudes = SolicitudRecuperacionExamen::query()
            ->where('examen_final_id', $examen->id)
            ->orderByDesc('id')
            ->get()
            ->groupBy('matricula_id');

        return ($examen->programacionAcademica?->matriculas ?? collect())
            ->sortBy(fn (Matricula $m) => $m->miembro?->nombre_completo)
            ->values()
            ->map(function (Matricula $matricula) use ($examen, $notas, $solicitudes): array {
                return [
                    'matricula' => $matricula,
                    'nota' => $notas->get($matricula->id),
                    'solicitud' => ($solicitudes->get($matricula->id) ?? collect())->first(),
                    'examen' => $examen,
                ];
            });
    }

    public function registrar(ExamenFinal $examen, Matricula $matricula, array $data, int $actor): NotaExamenFinal
    {
        if ((int) $matricula->programacion_academica_id !== (int) $examen->programacion_academica_id) {
            abort(403);
        }

        $this->assertNota($data['nota'] ?? null, $examen);

        return $this->transactions->execute(function () use ($examen, $matricula, $data, $actor): NotaExamenFinal {
            $registro = NotaExamenFinal::query()->firstOrNew([
                'examen_final_id' => $examen->id,
                'matricula_id' => $matricula->id,
            ]);
            $before = $registro->exists ? $registro->getAttributes() : null;
            $solicitud = $this->solicitudActiva($examen, $matricula);
            $esRecuperacion = $solicitud !== null && $solicitud->estado === SolicitudRecuperacionExamen::APROBADA;

            if ($esRecuperacion) {
                $registro->nota_recuperacion = $data['nota'];
                $registro->recuperacion_calificado_por_usuario_id = $actor;
                $registro->recuperacion_calificado_at = now();
                if ($registro->nota === null) {
                    $registro->nota = $data['nota'];
                    $registro->calificado_por_usuario_id = $actor;
                    $registro->calificado_at = now();
                }
                $solicitud->estado = SolicitudRecuperacionExamen::REALIZADA;
                $solicitud->save();
            } else {
                $registro->nota = $data['nota'];
                $registro->calificado_por_usuario_id = $actor;
                $registro->calificado_at = now();
                if (! $registro->exists) {
                    $registro->origen = NotaExamenFinal::ORIGEN_MANUAL;
                }
            }

            $registro->save();
            $this->auditorias->record(
                $actor,
                $before === null ? 'CREATE' : 'UPDATE',
                'notas_examen_final',
                $registro->id,
                $before,
                $registro->fresh()->getAttributes(),
            );

            $fresh = $registro->fresh(['matricula.miembro', 'calificadoPor.miembro', 'examenFinal']);
            $this->notificaciones->notaRegistrada($fresh, $actor, $esRecuperacion);

            return $fresh;
        });
    }

    public function upsertDesdeIntento(
        ExamenFinal $examen,
        Matricula $matricula,
        float $puntaje,
        int $actor,
        bool $esRecuperacion,
    ): NotaExamenFinal {
        $registro = NotaExamenFinal::query()->firstOrNew([
            'examen_final_id' => $examen->id,
            'matricula_id' => $matricula->id,
        ]);

        if ($esRecuperacion) {
            $registro->nota_recuperacion = $puntaje;
            $registro->recuperacion_calificado_por_usuario_id = $actor;
            $registro->recuperacion_calificado_at = now();
        } elseif ($registro->nota === null) {
            $registro->nota = $puntaje;
            $registro->calificado_por_usuario_id = $actor;
            $registro->calificado_at = now();
            $registro->origen = NotaExamenFinal::ORIGEN_INTERACTIVO;
        } else {
            return $registro->fresh(['matricula.miembro', 'calificadoPor.miembro', 'examenFinal']) ?? $registro;
        }

        $registro->save();

        $fresh = $registro->fresh(['matricula.miembro', 'calificadoPor.miembro', 'examenFinal']);
        $this->notificaciones->notaRegistrada($fresh, $actor, $esRecuperacion);

        return $fresh;
    }

    public function solicitudActiva(ExamenFinal $examen, Matricula $matricula): ?SolicitudRecuperacionExamen
    {
        return SolicitudRecuperacionExamen::query()
            ->where('examen_final_id', $examen->id)
            ->where('matricula_id', $matricula->id)
            ->whereIn('estado', SolicitudRecuperacionExamen::ACTIVAS)
            ->orderByDesc('id')
            ->first();
    }

    private function assertNota(mixed $nota, ExamenFinal $examen): void
    {
        if ($nota === null || ! is_numeric($nota)) {
            throw ValidationException::withMessages(['nota' => 'La nota es obligatoria.']);
        }

        if ((float) $nota < 0) {
            throw ValidationException::withMessages(['nota' => 'La nota no puede ser negativa.']);
        }

        if ((float) $nota > (float) $examen->puntaje_maximo) {
            throw ValidationException::withMessages(['nota' => 'La nota no puede superar el puntaje máximo del examen.']);
        }
    }
}
