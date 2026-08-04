<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Allowed transition between membership states. */
class TransicionEstadoMembresia extends Model
{
    use HasFactory;

    protected $table = 'transiciones_estado_membresia';

    protected $fillable = ['estado_origen_id', 'estado_destino_id', 'requiere_observacion', 'activo'];

    protected function casts(): array
    {
        return ['requiere_observacion' => 'boolean', 'activo' => 'boolean'];
    }

    public function origen(): BelongsTo
    {
        return $this->belongsTo(EstadoMembresia::class, 'estado_origen_id');
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(EstadoMembresia::class, 'estado_destino_id');
    }
}
