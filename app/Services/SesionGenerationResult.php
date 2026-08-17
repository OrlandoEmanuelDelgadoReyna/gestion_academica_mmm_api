<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Sesion;

/** Outcome of expanding recurring horarios into concrete session rows. */
final readonly class SesionGenerationResult
{
    /** @param list<Sesion> $createdSesiones */
    public function __construct(
        public int $created,
        public int $skipped,
        public int $total,
        public array $createdSesiones,
    ) {}
}
