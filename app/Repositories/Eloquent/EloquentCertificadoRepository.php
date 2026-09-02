<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Certificado;
use App\Models\Usuario;
use App\Repositories\Contracts\CertificadoRepositoryInterface;
use App\Services\AcademicAccess;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentCertificadoRepository implements CertificadoRepositoryInterface
{
    public function __construct(private AcademicAccess $academicAccess) {}

    public function paginateFor(Usuario $user, int $perPage, ?int $programacionAcademicaId = null, ?int $miembroId = null): LengthAwarePaginator
    {
        $query = Certificado::query()
            ->with(['miembro', 'tipoCertificado', 'programacionAcademica.curso.iglesia']);

        $this->academicAccess->constrainCertificados($query, $user);

        if ($programacionAcademicaId !== null) {
            $query->where('programacion_academica_id', $programacionAcademicaId);
        }

        if ($miembroId !== null) {
            $query->where('miembro_id', $miembroId);
        }

        return $query->orderByDesc('emitido_at')->paginate($perPage);
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
            ->with(['miembro.iglesia', 'tipoCertificado', 'programacionAcademica.curso.iglesia'])
            ->where('codigo_verificacion', $codigo)
            ->first();
    }

    public function delete(Certificado $certificado): void
    {
        $certificado->delete();
    }
}
