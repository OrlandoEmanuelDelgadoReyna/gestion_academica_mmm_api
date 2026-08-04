<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = ['codigo', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos')->withPivot(['asignado_por_usuario_id', 'asignado_at'])->withTimestamps();
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'usuario_roles')->withPivot(['asignado_por_usuario_id', 'asignado_at'])->withTimestamps();
    }
}
