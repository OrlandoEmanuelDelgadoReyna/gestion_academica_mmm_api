<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catalog of roles that a member may perform in a worship service. */
class TipoParticipacion extends Model
{
    use HasFactory;

    protected $table = 'tipos_participacion';

    protected $fillable = ['codigo', 'nombre', 'requiere_miembro', 'activo'];

    protected function casts(): array
    {
        return ['requiere_miembro' => 'boolean', 'activo' => 'boolean'];
    }

    public function participaciones(): HasMany
    {
        return $this->hasMany(BloqueCulto::class);
    }
}
