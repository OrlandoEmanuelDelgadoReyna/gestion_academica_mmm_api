<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AuditoriaRepositoryInterface
{
    public function record(?int $usuarioId, string $accion, string $tabla, ?int $registroId, ?array $antes, ?array $despues): void;
}
