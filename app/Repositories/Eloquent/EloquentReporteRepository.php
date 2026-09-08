<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Calificacion;
use App\Models\Certificado;
use App\Models\Curso;
use App\Models\Iglesia;
use App\Models\Matricula;
use App\Models\Miembro;
use App\Models\Usuario;
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

        $matriculasTotal = Matricula::query()->count();

        return [
            'matriculas' => [
                'total' => $matriculasTotal,
                'por_estado' => $matriculasPorEstado,
            ],
            'calificaciones' => [
                'total' => Calificacion::query()->count(),
                'por_estado' => $calificacionesPorEstado,
                'promedio_nota_final' => Calificacion::query()->avg('nota_final'),
            ],
            'certificados' => [
                'emitidos' => Certificado::query()
                    ->where('estado', Certificado::ESTADO_EMITIDO)
                    ->whereNotNull('programacion_academica_id')
                    ->count(),
                'universo' => $matriculasTotal,
            ],
        ];
    }

    public function administrativosSummary(): array
    {
        $usuariosTotal = Usuario::query()->count();
        $usuariosActivos = Usuario::query()->where('activo', true)->count();
        $cursosTotal = Curso::query()->count();
        $cursosActivos = Curso::query()->where('activo', true)->count();

        return [
            'iglesias' => [
                'total' => Iglesia::query()->count(),
                'activas' => Iglesia::query()->where('activo', true)->count(),
            ],
            'miembros' => [
                'total' => Miembro::query()->count(),
            ],
            'usuarios' => [
                'total' => $usuariosTotal,
                'activos' => $usuariosActivos,
                'inactivos' => $usuariosTotal - $usuariosActivos,
            ],
            'cursos' => [
                'total' => $cursosTotal,
                'activos' => $cursosActivos,
                'inactivos' => $cursosTotal - $cursosActivos,
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
