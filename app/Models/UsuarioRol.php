<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assignment history metadata for a user role. */
class UsuarioRol extends Model
{
    protected $table = 'usuario_roles';

    protected $fillable = ['usuario_id', 'rol_id', 'asignado_por_usuario_id', 'asignado_at'];

    protected function casts(): array
    {
        return ['asignado_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function asignadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'asignado_por_usuario_id');
    }
}
