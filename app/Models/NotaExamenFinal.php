<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registered exam score for one enrollment. Distinct from interactive attempts. */
class NotaExamenFinal extends Model
{
    use HasFactory;

    public const ORIGEN_MANUAL = 'manual';

    public const ORIGEN_INTERACTIVO = 'interactivo';

    protected $table = 'notas_examen_final';

    protected $fillable = [
        'examen_final_id',
        'matricula_id',
        'nota',
        'nota_recuperacion',
        'calificado_por_usuario_id',
        'calificado_at',
        'recuperacion_calificado_por_usuario_id',
        'recuperacion_calificado_at',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'nota' => 'decimal:2',
            'nota_recuperacion' => 'decimal:2',
            'calificado_at' => 'datetime',
            'recuperacion_calificado_at' => 'datetime',
        ];
    }

    public function examenFinal(): BelongsTo
    {
        return $this->belongsTo(ExamenFinal::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function calificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'calificado_por_usuario_id');
    }

    public function recuperacionCalificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recuperacion_calificado_por_usuario_id');
    }

    public function notaConsiderada(): ?float
    {
        $original = $this->nota !== null ? (float) $this->nota : null;
        $recuperacion = $this->nota_recuperacion !== null ? (float) $this->nota_recuperacion : null;

        if ($original === null && $recuperacion === null) {
            return null;
        }

        if ($original === null) {
            return $recuperacion;
        }

        if ($recuperacion === null) {
            return $original;
        }

        return max($original, $recuperacion);
    }

    public function resultado(?float $minima): ?string
    {
        $nota = $this->notaConsiderada();
        if ($nota === null || $minima === null) {
            return null;
        }

        return $nota + 0.0 >= $minima ? 'aprobado' : 'desaprobado';
    }

    public function puntosFaltantes(?float $minima): ?float
    {
        $nota = $this->notaConsiderada();
        if ($nota === null || $minima === null) {
            return null;
        }

        if ($nota >= $minima) {
            return 0.0;
        }

        return round($minima - $nota, 2);
    }
}
