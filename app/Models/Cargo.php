<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Cargo extends Model
{
    protected $fillable = ['iglesia_id', 'codigo', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'miembro_cargos')->withPivot(['fecha_inicio', 'fecha_fin', 'activo', 'observacion'])->withTimestamps();
    }
}
