<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membership state allowed to enroll in a program. */
class ProgramacionEstadoMembresiaPermitido extends Model
{
    protected $table = 'programacion_estados_membresia_permitidos';

    protected $fillable = ['programacion_academica_id', 'estado_membresia_id'];

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function estadoMembresia(): BelongsTo
    {
        return $this->belongsTo(EstadoMembresia::class);
    }
}
