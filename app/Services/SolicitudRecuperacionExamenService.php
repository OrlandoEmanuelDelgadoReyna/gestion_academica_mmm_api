<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\Matricula;
use App\Models\NotaExamenFinal;
use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Exam recovery request workflow. */
final class SolicitudRecuperacionExamenService
{
    public function __construct(
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private ExamenNotificacionDispatcher $notificaciones,
    ) {}

    public function solicitar(ExamenFinal $examen, Usuario $actor): SolicitudRecuperacionExamen
    {
        $matricula = $this->resolveOwnedActiveMatricula($actor, $examen);
        $this->assertPuedeSolicitar($examen, $matricula);

        return $this->transactions->execute(function () use ($examen, $matricula, $actor): SolicitudRecuperacionExamen {
            $solicitud = SolicitudRecuperacionExamen::query()->create([
                'examen_final_id' => $examen->id,
                'matricula_id' => $matricula->id,
                'estado' => SolicitudRecuperacionExamen::PENDIENTE,
                'solicitada_at' => now(),
            ]);
            $this->auditorias->record($actor->id, 'CREATE', 'solicitudes_recuperacion_examen', $solicitud->id, null, $solicitud->getAttributes());
            $this->notificaciones->recuperacionSolicitada($solicitud->load(['examenFinal', 'matricula.miembro']), $actor->id);

            return $solicitud->load(['examenFinal', 'matricula.miembro']);
        });
    }

    public function atender(SolicitudRecuperacionExamen $solicitud, array $data, int $actor): SolicitudRecuperacionExamen
    {
        $estado = $data['estado'] ?? null;
        if (! in_array($estado, [SolicitudRecuperacionExamen::APROBADA, SolicitudRecuperacionExamen::RECHAZADA], true)) {
            throw ValidationException::withMessages(['estado' => 'El estado debe ser aprobada o rechazada.']);
        }

        if ($solicitud->estado !== SolicitudRecuperacionExamen::PENDIENTE) {
            throw ValidationException::withMessages(['estado' => 'Solo se puede atender una solicitud pendiente.']);
        }

        return $this->transactions->execute(function () use ($solicitud, $data, $actor, $estado): SolicitudRecuperacionExamen {
            $before = $solicitud->getAttributes();
            $solicitud->update([
                'estado' => $estado,
                'observacion' => $data['observacion'] ?? $solicitud->observacion,
                'atendida_por_usuario_id' => $actor,
                'atendida_at' => now(),
            ]);
            $this->auditorias->record($actor, 'UPDATE', 'solicitudes_recuperacion_examen', $solicitud->id, $before, $solicitud->fresh()->getAttributes());
            $fresh = $solicitud->fresh(['examenFinal', 'matricula.miembro', 'atendidaPor.miembro']);
            $this->notificaciones->recuperacionAtendida($fresh, $actor);

            return $fresh;
        });
    }

    /** @return Collection<int, SolicitudRecuperacionExamen> */
    public function listarPorExamen(ExamenFinal $examen): Collection
    {
        return SolicitudRecuperacionExamen::query()
            ->where('examen_final_id', $examen->id)
            ->with(['matricula.miembro', 'atendidaPor.miembro'])
            ->orderByDesc('solicitada_at')
            ->get();
    }

    private function resolveOwnedActiveMatricula(Usuario $actor, ExamenFinal $examen): Matricula
    {
        if ($actor->miembro_id === null) {
            abort(403);
        }

        $matricula = Matricula::query()
            ->where('miembro_id', $actor->miembro_id)
            ->where('programacion_academica_id', $examen->programacion_academica_id)
            ->where('estado', 'activa')
            ->first();

        if ($matricula === null) {
            abort(403);
        }

        return $matricula;
    }

    private function assertPuedeSolicitar(ExamenFinal $examen, Matricula $matricula): void
    {
        $registro = NotaExamenFinal::query()
            ->where('examen_final_id', $examen->id)
            ->where('matricula_id', $matricula->id)
            ->first();

        if ($registro === null || $registro->nota === null) {
            throw ValidationException::withMessages(['solicitud' => 'No puedes solicitar recuperación sin una nota del examen.']);
        }

        $minima = (float) $examen->nota_minima_aprobatoria;
        if ((float) $registro->notaConsiderada() >= $minima) {
            throw ValidationException::withMessages(['solicitud' => 'No corresponde solicitar recuperación porque el examen está aprobado.']);
        }

        $activa = SolicitudRecuperacionExamen::query()
            ->where('examen_final_id', $examen->id)
            ->where('matricula_id', $matricula->id)
            ->whereIn('estado', SolicitudRecuperacionExamen::ACTIVAS)
            ->exists();

        if ($activa) {
            throw ValidationException::withMessages(['solicitud' => 'Ya existe una solicitud de recuperación activa.']);
        }
    }
}
