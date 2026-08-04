<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HistorialMembresia extends Model
{
    protected $table = 'historial_membresia';

    protected $fillable = ['miembro_id', 'estado_membresia_id', 'fecha_inicio', 'fecha_fin', 'observacion', 'registrado_por_usuario_id'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoMembresia::class, 'estado_membresia_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }
}
