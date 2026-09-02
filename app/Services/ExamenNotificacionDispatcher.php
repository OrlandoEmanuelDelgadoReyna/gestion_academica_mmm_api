<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ExamenFinal;
use App\Models\NotaExamenFinal;
use App\Models\SolicitudRecuperacionExamen;
use App\Models\Usuario;

/** Dispatches exam-related notices through the existing notification tables. */
final class ExamenNotificacionDispatcher
{
    public function __construct(
        private NotificacionService $notificaciones,
        private AcademicAccess $academicAccess,
    ) {}

    public function recuperacionSolicitada(SolicitudRecuperacionExamen $solicitud, int $actorId): void
    {
        $examen = $solicitud->examenFinal;
        $alumno = $solicitud->matricula?->miembro?->nombre_completo ?? 'Un alumno';
        $nota = NotaExamenFinal::query()
            ->where('examen_final_id', $solicitud->examen_final_id)
            ->where('matricula_id', $solicitud->matricula_id)
            ->first();
        $contenido = sprintf(
            "%s solicitó recuperación del examen %s.\nNota obtenida: %s\nMínima aprobatoria: %s\nFecha: %s\nEstado: pendiente.",
            $alumno,
            $examen?->titulo ?? 'Examen',
            $nota?->nota !== null ? (string) $nota->nota : '—',
            $examen?->nota_minima_aprobatoria !== null ? (string) $examen->nota_minima_aprobatoria : '—',
            optional($solicitud->solicitada_at)->toDateTimeString() ?? now()->toDateTimeString(),
        );

        $this->dispatch(
            $examen,
            'Solicitud de recuperación de examen',
            $contenido,
            $this->staffUsuarioIds($examen),
            $actorId,
        );
    }

    public function recuperacionAtendida(SolicitudRecuperacionExamen $solicitud, int $actorId): void
    {
        $examen = $solicitud->examenFinal;
        $alumnoUsuario = $this->usuarioDeMatricula($solicitud->matricula_id);
        if ($alumnoUsuario === null || $examen === null) {
            return;
        }

        $titulo = $solicitud->estado === SolicitudRecuperacionExamen::APROBADA
            ? 'Recuperación aprobada'
            : 'Recuperación rechazada';
        $contenido = sprintf(
            "Tu solicitud de recuperación del examen %s fue %s.%s",
            $examen->titulo,
            $solicitud->estado,
            filled($solicitud->observacion) ? "\nObservación: {$solicitud->observacion}" : '',
        );

        $this->dispatch($examen, $titulo, $contenido, [$alumnoUsuario], $actorId);
    }

    public function notaRegistrada(NotaExamenFinal $nota, int $actorId, bool $esRecuperacion): void
    {
        $examen = $nota->examenFinal;
        $alumnoUsuario = $this->usuarioDeMatricula($nota->matricula_id);
        if ($alumnoUsuario === null || $examen === null) {
            return;
        }

        $titulo = $esRecuperacion ? 'Recuperación calificada' : 'Nota de examen registrada';
        $valor = $esRecuperacion ? $nota->nota_recuperacion : $nota->nota;
        $contenido = sprintf(
            "Se registró tu %s del examen %s: %s / %s.",
            $esRecuperacion ? 'nota de recuperación' : 'nota',
            $examen->titulo,
            $valor !== null ? (string) $valor : '—',
            (string) $examen->puntaje_maximo,
        );

        $this->dispatch($examen, $titulo, $contenido, [$alumnoUsuario], $actorId);
    }

    /** @param list<int> $usuarioIds */
    private function dispatch(?ExamenFinal $examen, string $titulo, string $contenido, array $usuarioIds, int $actorId): void
    {
        $usuarioIds = array_values(array_unique(array_filter($usuarioIds)));
        if ($examen === null || $usuarioIds === []) {
            return;
        }

        $examen->loadMissing('programacionAcademica.curso');
        $iglesiaId = $examen->programacionAcademica?->curso?->iglesia_id;
        if ($iglesiaId === null) {
            return;
        }

        $usuarioIds = $this->academicAccess->constrainUsuarioIdsToIglesia((int) $iglesiaId, $usuarioIds);
        if ($usuarioIds === []) {
            return;
        }

        $this->notificaciones->dispatch([
            'iglesia_id' => $iglesiaId,
            'titulo' => mb_substr($titulo, 0, 150),
            'contenido' => $contenido,
            'tipo' => 'examen',
        ], $usuarioIds, $actorId);
    }

    /** @return list<int> */
    private function staffUsuarioIds(?ExamenFinal $examen): array
    {
        if ($examen === null) {
            return [];
        }

        $examen->loadMissing('programacionAcademica.docentes');
        $miembroIds = $examen->programacionAcademica?->docentes->pluck('id') ?? collect();

        $docentes = Usuario::query()->whereIn('miembro_id', $miembroIds)->pluck('id');
        $admins = Usuario::query()
            ->whereHas(
                'roles.permisos',
                fn ($query) => $query->where('codigo', 'academico.gestionar')->where('activo', true),
            )
            ->pluck('id');

        return $docentes->merge($admins)->unique()->values()->all();
    }

    private function usuarioDeMatricula(int $matriculaId): ?int
    {
        $miembroId = \App\Models\Matricula::query()->whereKey($matriculaId)->value('miembro_id');
        if ($miembroId === null) {
            return null;
        }

        return Usuario::query()->where('miembro_id', $miembroId)->value('id');
    }
}
