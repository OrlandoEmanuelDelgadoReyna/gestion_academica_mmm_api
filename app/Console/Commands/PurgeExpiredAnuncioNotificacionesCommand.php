<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

final class PurgeExpiredAnuncioNotificacionesCommand extends Command
{
    protected $signature = 'notificaciones:purge-expired-anuncios';

    protected $description = 'Elimina notificaciones de anuncios vencidos al día siguiente de vence_at (00:00 America/Lima).';

    public function handle(NotificacionService $notificaciones): int
    {
        $purged = $notificaciones->purgeExpiredAnuncioNotificaciones();
        $this->info("Notificaciones de anuncios vencidos eliminadas: {$purged}.");

        return self::SUCCESS;
    }
}
