<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = ['codigo', 'modulo', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos')->withPivot(['asignado_por_usuario_id', 'asignado_at'])->withTimestamps();
    }
}
