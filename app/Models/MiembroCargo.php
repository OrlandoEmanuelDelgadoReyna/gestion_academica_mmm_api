<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Time-bounded appointment of a member to a church position. */
class MiembroCargo extends Model
{
    protected $table = 'miembro_cargos';

    protected $fillable = ['miembro_id', 'cargo_id', 'fecha_inicio', 'fecha_fin', 'activo', 'observacion'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'activo' => 'boolean'];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }
}
