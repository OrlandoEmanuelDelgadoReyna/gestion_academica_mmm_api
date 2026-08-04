<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Teaching assignment for an academic program. */
class ProgramacionDocente extends Model
{
    protected $table = 'programacion_docentes';

    protected $fillable = ['programacion_academica_id', 'miembro_id', 'asignado_at'];

    protected function casts(): array
    {
        return ['asignado_at' => 'datetime'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }
}
