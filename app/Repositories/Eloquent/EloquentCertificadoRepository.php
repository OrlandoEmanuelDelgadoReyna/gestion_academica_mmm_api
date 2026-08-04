<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Certificado;
use App\Repositories\Contracts\CertificadoRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCertificadoRepository implements CertificadoRepositoryInterface
{
    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Certificado::query()
            ->with(['miembro', 'tipoCertificado', 'programacionAcademica'])
            ->orderByDesc('emitido_at')
            ->paginate($perPage);
    }

    public function create(array $data): Certificado
    {
        return Certificado::query()->create($data);
    }

    public function update(Certificado $certificado, array $data): Certificado
    {
        $certificado->update($data);

        return $certificado->refresh();
    }

    public function findByCodigo(string $codigo): ?Certificado
    {
        return Certificado::query()
            ->with(['miembro', 'tipoCertificado', 'programacionAcademica'])
            ->where('codigo_verificacion', $codigo)
            ->first();
    }
}
