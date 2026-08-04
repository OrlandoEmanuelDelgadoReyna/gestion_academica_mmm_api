<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Certificado;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CertificadoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator;

    public function create(array $data): Certificado;

    public function update(Certificado $certificado, array $data): Certificado;

    public function findByCodigo(string $codigo): ?Certificado;
}
