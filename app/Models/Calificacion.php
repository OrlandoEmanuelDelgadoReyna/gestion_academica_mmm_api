<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Consolidated final grade for an enrollment. */
class Calificacion extends Model
{
    use HasFactory;

    protected $table = 'calificaciones';

    protected $fillable = ['matricula_id', 'promedio_tareas', 'nota_examen_final', 'nota_final', 'estado', 'calculado_at'];

    protected function casts(): array
    {
        return ['promedio_tareas' => 'decimal:2', 'nota_examen_final' => 'decimal:2', 'nota_final' => 'decimal:2', 'calculado_at' => 'datetime'];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }
}
