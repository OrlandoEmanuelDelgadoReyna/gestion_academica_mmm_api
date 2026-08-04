<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assignment metadata for a permission granted to a role. */
class RolPermiso extends Model
{
    protected $table = 'rol_permisos';

    protected $fillable = ['rol_id', 'permiso_id', 'asignado_por_usuario_id', 'asignado_at'];

    protected function casts(): array
    {
        return ['asignado_at' => 'datetime'];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function permiso(): BelongsTo
    {
        return $this->belongsTo(Permiso::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_por_usuario_id');
    }
}
