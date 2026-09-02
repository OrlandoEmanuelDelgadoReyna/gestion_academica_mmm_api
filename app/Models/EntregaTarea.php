<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A student submission and its evaluation. */
class EntregaTarea extends Model
{
    use HasFactory;

    protected $table = 'entregas_tarea';

    protected $fillable = [
        'tarea_id',
        'matricula_id',
        'contenido',
        'ruta_archivo',
        'nombre_original',
        'mime',
        'tamano_bytes',
        'entregado_at',
        'nota',
        'retroalimentacion',
        'calificado_at',
        'calificado_por_usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'entregado_at' => 'datetime',
            'nota' => 'decimal:2',
            'calificado_at' => 'datetime',
            'tamano_bytes' => 'integer',
        ];
    }

    public function isCalificada(): bool
    {
        return $this->nota !== null || $this->calificado_at !== null;
    }

    public function hasArchivo(): bool
    {
        return filled($this->ruta_archivo);
    }

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function calificadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'calificado_por_usuario_id');
    }
}
