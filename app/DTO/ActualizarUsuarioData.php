<?php

declare(strict_types=1);

namespace App\DTO;

/** Immutable input for the user-update use case. */
final readonly class ActualizarUsuarioData
{
    /** @param list<int> $rolIds */
    public function __construct(
        public string $nombreUsuario,
        public bool $activo,
        public array $rolIds,
    ) {}
}
