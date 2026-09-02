<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Certificado;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CertificadoRepositoryInterface
{
    public function paginateFor(Usuario $user, int $perPage, ?int $programacionAcademicaId = null, ?int $miembroId = null): LengthAwarePaginator;

    public function create(array $data): Certificado;

    public function update(Certificado $certificado, array $data): Certificado;

    public function findByCodigo(string $codigo): ?Certificado;

    public function delete(Certificado $certificado): void;
}
