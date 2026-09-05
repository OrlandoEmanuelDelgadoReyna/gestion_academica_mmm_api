<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use App\Repositories\Contracts\ExamenFinalRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/** Transactional application service for final exam configuration. */
final class ExamenFinalService
{
    public function __construct(
        private ExamenFinalRepositoryInterface $examenes,
        private DatabaseTransactionRepositoryInterface $transactions,
        private AuditoriaRepositoryInterface $auditorias,
        private ExamenNotificacionDispatcher $notificaciones,
    ) {}

    public function paginate(
        int $perPage,
        ?int $programacionAcademicaId = null,
        ?int $assignedMiembroId = null,
        ?int $enrolledMiembroId = null,
    ): LengthAwarePaginator {
        return $this->examenes->paginate($perPage, $programacionAcademicaId, $assignedMiembroId, $enrolledMiembroId);
    }

    public function create(array $data, int $actor): ExamenFinal
    {
        $examen = $this->transactions->execute(function () use ($data, $actor): ExamenFinal {
            if (ExamenFinal::query()->where('programacion_academica_id', $data['programacion_academica_id'])->exists()) {
                throw ValidationException::withMessages(['programacion_academica_id' => 'Ya existe un examen final para esta programación.']);
            }

            $this->assertNotaMinima($data['nota_minima_aprobatoria'] ?? null, $data['puntaje_maximo'] ?? null);
            $data['creado_por_usuario_id'] = $actor;
            $examen = $this->examenes->create($data);
            $this->auditorias->record($actor, 'CREATE', 'examenes_finales', $examen->id, null, $examen->getAttributes());

            return $examen->load(['programacionAcademica.curso', 'creadoPor.miembro']);
        });

        $this->notificaciones->examenProgramado($examen, $actor);

        return $examen;
    }

    public function update(ExamenFinal $examen, array $data, int $actor): ExamenFinal
    {
        return $this->transactions->execute(function () use ($examen, $data, $actor): ExamenFinal {
            unset(
                $data['programacion_academica_id'],
                $data['creado_por_usuario_id'],
                $data['recuperacion_titulo'],
                $data['recuperacion_descripcion'],
                $data['recuperacion_at'],
                $data['recuperacion_generada_at'],
            );
            $max = array_key_exists('puntaje_maximo', $data) ? $data['puntaje_maximo'] : $examen->puntaje_maximo;
            $min = array_key_exists('nota_minima_aprobatoria', $data) ? $data['nota_minima_aprobatoria'] : $examen->nota_minima_aprobatoria;
            $this->assertNotaMinima($min, $max);
            $before = $examen->getAttributes();
            $updated = $this->examenes->update($examen, $data);
            $this->auditorias->record($actor, 'UPDATE', 'examenes_finales', $updated->id, $before, $updated->getAttributes());

            return $updated->load(['programacionAcademica', 'creadoPor.miembro']);
        });
    }

    public function generarRecuperacion(ExamenFinal $examen, array $data, int $actor): ExamenFinal
    {
        $examen = $this->transactions->execute(function () use ($examen, $data, $actor): ExamenFinal {
            $examen->refresh();
            if ($examen->tieneRecuperacionGenerada()) {
                throw ValidationException::withMessages([
                    'recuperacion' => 'Ya existe un examen de recuperación para este examen.',
                ]);
            }

            $before = $examen->getAttributes();
            $updated = $this->examenes->update($examen, [
                'recuperacion_titulo' => $data['titulo'],
                'recuperacion_descripcion' => $data['descripcion'] ?? null,
                'recuperacion_at' => $data['fecha'],
                'recuperacion_generada_at' => now(),
            ]);
            $this->auditorias->record($actor, 'UPDATE', 'examenes_finales', $updated->id, $before, $updated->getAttributes());

            return $updated->load(['programacionAcademica.curso', 'creadoPor.miembro', 'notas']);
        });

        $this->notificaciones->recuperacionGenerada($examen, $actor);

        return $examen;
    }

    public function delete(ExamenFinal $examen, int $actor): void
    {
        $this->transactions->execute(function () use ($examen, $actor): void {
            $before = $examen->getAttributes();
            $this->examenes->delete($examen);
            $this->auditorias->record($actor, 'DELETE', 'examenes_finales', $examen->id, $before, null);
        });
    }

    private function assertNotaMinima(mixed $minima, mixed $maximo): void
    {
        if ($minima === null || $maximo === null) {
            return;
        }

        if ((float) $minima > (float) $maximo) {
            throw ValidationException::withMessages([
                'nota_minima_aprobatoria' => 'La nota mínima no puede superar el puntaje máximo.',
            ]);
        }
    }
}
