<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificado;
use App\Models\Usuario;

/** Dispatches certificate notices through the existing notification tables. */
final class CertificadoNotificacionDispatcher
{
    public function __construct(
        private NotificacionService $notificaciones,
        private AcademicAccess $academicAccess,
    ) {}

    public function certificadoDisponible(Certificado $certificado, int $actorId): void
    {
        $certificado->loadMissing(['miembro.usuario', 'programacionAcademica.curso']);

        $iglesiaId = $certificado->programacionAcademica?->curso?->iglesia_id
            ?? $certificado->miembro?->iglesia_id;
        $alumnoId = $certificado->miembro?->usuario?->id
            ?? Usuario::query()->where('miembro_id', $certificado->miembro_id)->value('id');

        if ($iglesiaId === null || $alumnoId === null) {
            return;
        }

        $curso = $certificado->programacionAcademica?->curso?->nombre ?? 'tu curso';

        $this->dispatch(
            (int) $iglesiaId,
            'Certificado disponible',
            sprintf('Tu certificado de %s está disponible.', $curso),
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
            'tipo' => 'certificado',
        ], $usuarioIds, $actorId);
    }
}
