<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

/**
 * Temporary one-shot cleanup for announcement notices created before anuncio_id.
 * Preview by default. Deletes only with --force. Do not add this to the scheduler.
 */
final class PurgeLegacyAnuncioNotificacionesCommand extends Command
{
    protected $signature = 'notificaciones:purge-legacy-anuncios
                            {--force : Elimina las filas listadas. Sin esta opción solo se previsualiza.}';

    protected $description = 'Previsualiza o elimina notificaciones tipo anuncio que todavía no tienen anuncio_id.';

    public function handle(NotificacionService $notificaciones): int
    {
        $rows = $notificaciones->listLegacyAnuncioNotificaciones();

        $this->info('Notificaciones legacy (tipo=anuncio AND anuncio_id IS NULL): '.$rows->count());

        if ($rows->isEmpty()) {
            $this->comment('No hay filas que coincidan. Nada que eliminar.');

            return self::SUCCESS;
        }

        $this->table(
            ['id', 'titulo', 'iglesia_id', 'enviado_at'],
            $rows->map(fn ($row): array => [
                $row->id,
                $row->titulo,
                $row->iglesia_id,
                $row->enviado_at?->toDateTimeString(),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->comment('Nada fue eliminado. Ejecute con --force para borrar únicamente estas filas y sus destinatarios.');

            return self::SUCCESS;
        }

        $deleted = $notificaciones->deleteLegacyAnuncioNotificaciones();
        $this->info('Notificaciones eliminadas: '.$deleted['notificaciones']);
        $this->info('Destinatarios eliminados: '.$deleted['destinatarios']);

        return self::SUCCESS;
    }
}
