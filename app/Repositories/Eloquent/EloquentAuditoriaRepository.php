<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Auditoria;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;

final class EloquentAuditoriaRepository implements AuditoriaRepositoryInterface
{
    public function record(?int $usuarioId, string $accion, string $tabla, ?int $registroId, ?array $antes, ?array $despues): void
    {
        Auditoria::query()->create(['usuario_id' => $usuarioId, 'accion' => $accion, 'tabla_afectada' => $tabla, 'registro_id' => $registroId, 'datos_antes' => $antes, 'datos_despues' => $despues, 'direccion_ip' => request()?->ip(), 'dispositivo' => request()?->userAgent()]);
    }
}
