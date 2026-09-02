<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\EntregaTarea;
use App\Models\Matricula;
use App\Models\Tarea;
use App\Models\Usuario;

/** Dispatches task notices through the existing notification tables. */
final class TareaNotificacionDispatcher
{
    public function __construct(
        private NotificacionService $notificaciones,
        private AcademicAccess $academicAccess,
    ) {}

    public function tareaPublicada(Tarea $tarea, int $actorId): void
    {
        $tarea->loadMissing('programacionAcademica.curso');
        $iglesiaId = $tarea->programacionAcademica?->curso?->iglesia_id;
        if ($iglesiaId === null) {
            return;
        }

        $alumnoIds = Usuario::query()
            ->where('activo', true)
            ->whereIn('miembro_id', Matricula::query()
                ->where('programacion_academica_id', $tarea->programacion_academica_id)
                ->where('estado', 'activa')
                ->select('miembro_id'))
            ->pluck('id')
            ->all();

        $this->dispatch(
            (int) $iglesiaId,
            'Nueva tarea publicada',
            sprintf('Se publicó la tarea "%s".', $tarea->titulo),
            $alumnoIds,
            $actorId,
        );
    }

    public function entregaRealizada(EntregaTarea $entrega, int $actorId): void
    {
        $entrega->loadMissing(['tarea.programacionAcademica.curso', 'tarea.programacionAcademica.docentes', 'matricula.miembro']);
        $tarea = $entrega->tarea;
        $iglesiaId = $tarea?->programacionAcademica?->curso?->iglesia_id;
        if ($tarea === null || $iglesiaId === null) {
            return;
        }

        $miembroIds = $tarea->programacionAcademica?->docentes->pluck('id') ?? collect();
        $docenteIds = Usuario::query()->whereIn('miembro_id', $miembroIds)->pluck('id')->all();
        $alumnoNombre = $entrega->matricula?->miembro?->nombre_completo ?? 'Un alumno';

        $this->dispatch(
            (int) $iglesiaId,
            'Nueva entrega de tarea',
            sprintf('%s entregó la tarea "%s".', $alumnoNombre, $tarea->titulo),
            $docenteIds,
            $actorId,
        );
    }

    public function entregaCalificada(EntregaTarea $entrega, int $actorId): void
    {
        $entrega->loadMissing(['tarea.programacionAcademica.curso', 'matricula']);
        $tarea = $entrega->tarea;
        $iglesiaId = $tarea?->programacionAcademica?->curso?->iglesia_id;
        $miembroId = $entrega->matricula?->miembro_id;
        if ($tarea === null || $iglesiaId === null || $miembroId === null) {
            return;
        }

        $alumnoId = Usuario::query()->where('miembro_id', $miembroId)->value('id');
        if ($alumnoId === null) {
            return;
        }

        $nota = $entrega->nota !== null ? (string) $entrega->nota : '—';
        $this->dispatch(
            (int) $iglesiaId,
            'Tarea calificada',
            sprintf('Tu entrega de "%s" fue calificada: %s / %s.', $tarea->titulo, $nota, (string) $tarea->puntaje_maximo),
            [(int) $alumnoId],
            $actorId,
        );
    }

    /** @param  list<int>  $usuarioIds */
    private function dispatch(int $iglesiaId, string $titulo, string $contenido, array $usuarioIds, int $actorId): void
    {
        $usuarioIds = $this->academicAccess->constrainUsuarioIdsToIglesia($iglesiaId, $usuarioIds);
        if ($usuarioIds === []) {
            return;
        }

        $this->notificaciones->dispatch([
            'iglesia_id' => $iglesiaId,
            'titulo' => mb_substr($titulo, 0, 150),
            'contenido' => $contenido,
            'tipo' => 'tarea',
        ], $usuarioIds, $actorId);
    }
}
