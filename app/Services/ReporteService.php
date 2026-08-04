<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ReporteRepositoryInterface;

final class ReporteService
{
    public function __construct(private ReporteRepositoryInterface $reportes) {}

    /** @return array<string, mixed> */
    public function academicos(): array
    {
        return $this->reportes->academicosSummary();
    }

    /** @return array<string, mixed> */
    public function administrativos(): array
    {
        return $this->reportes->administrativosSummary();
    }

    /** @return list<array<string, mixed>> */
    public function certificados(): array
    {
        return $this->reportes->certificadosEmitidos()->values()->all();
    }
}
