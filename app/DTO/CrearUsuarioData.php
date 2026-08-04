<?php

declare(strict_types=1);

namespace App\DTO;

/** Immutable input for the user-registration use case. */
final readonly class CrearUsuarioData
{
    /** @param list<int> $rolIds */
    public function __construct(public int $miembroId, public string $nombreUsuario, public string $contrasena, public array $rolIds) {}
}
