<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\Iglesia;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Repositories\Contracts\ReporteRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentReporteRepository implements ReporteRepositoryInterface
{
    public function academicosSummary(): array
    {
        $matriculasPorEstado = Matricula::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $calificacionesPorEstado = Calificacion::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'matriculas' => [
                'total' => Matricula::query()->count(),
                'por_estado' => $matriculasPorEstado,
            ],
            'calificaciones' => [
                'total' => Calificacion::query()->count(),
                'por_estado' => $calificacionesPorEstado,
                'promedio_nota_final' => Calificacion::query()->avg('nota_final'),
            ],
        ];
    }

    public function administrativosSummary(): array
    {
        return [
            'iglesias' => [
                'total' => Iglesia::query()->count(),
                'activas' => Iglesia::query()->where('activo', true)->count(),
            ],
            'miembros' => [
                'total' => Miembro::query()->count(),
            ],
        ];
    }

    public function certificadosEmitidos(): Collection
    {
        return Certificado::query()
            ->with(['miembro', 'tipoCertificado'])
            ->orderByDesc('emitido_at')
            ->get()
            ->map(fn (Certificado $certificado): array => [
                'id' => $certificado->id,
                'codigo_verificacion' => $certificado->codigo_verificacion,
                'estado' => $certificado->estado,
                'emitido_at' => $certificado->emitido_at,
                'miembro' => $certificado->miembro?->only(['id', 'nombres', 'apellidos']),
                'tipo_certificado' => $certificado->tipoCertificado?->only(['id', 'codigo', 'nombre']),
            ]);
    }
}
