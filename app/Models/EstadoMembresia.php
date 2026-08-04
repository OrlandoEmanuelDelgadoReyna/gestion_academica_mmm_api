<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EstadoMembresia extends Model
{
    protected $table = 'estados_membresia';

    protected $fillable = ['codigo', 'nombre', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function historiales(): HasMany
    {
        return $this->hasMany(HistorialMembresia::class);
    }
}
