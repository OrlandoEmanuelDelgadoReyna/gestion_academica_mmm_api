<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Weighted component of a program's final grade. */
class CriterioEvaluacion extends Model
{
    use HasFactory;

    protected $table = 'criterios_evaluacion';

    protected $fillable = ['programacion_academica_id', 'codigo', 'origen', 'nombre', 'porcentaje', 'orden'];

    protected function casts(): array
    {
        return ['porcentaje' => 'decimal:2', 'orden' => 'integer'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }
}
