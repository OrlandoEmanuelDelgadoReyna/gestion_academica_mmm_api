<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface ReporteRepositoryInterface
{
    /** @return array<string, mixed> */
    public function academicosSummary(): array;

    /** @return array<string, mixed> */
    public function administrativosSummary(): array;

    /** @return Collection<int, array<string, mixed>> */
    public function certificadosEmitidos(): Collection;
}
