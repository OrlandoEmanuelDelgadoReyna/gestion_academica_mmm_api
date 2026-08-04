<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Membership of a person in a church society. */
class MiembroSociedad extends Model
{
    protected $table = 'miembro_sociedades';

    protected $fillable = ['miembro_id', 'sociedad_id', 'fecha_ingreso', 'fecha_salida', 'activo'];

    protected function casts(): array
    {
        return ['fecha_ingreso' => 'date', 'fecha_salida' => 'date', 'activo' => 'boolean'];
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function sociedad(): BelongsTo
    {
        return $this->belongsTo(Sociedad::class);
    }
}
