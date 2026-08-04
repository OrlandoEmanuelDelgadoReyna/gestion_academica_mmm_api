<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

final class Usuario extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = ['miembro_id', 'nombre_usuario', 'contrasena', 'activo', 'ultimo_acceso_at'];

    protected $hidden = ['contrasena'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'ultimo_acceso_at' => 'datetime', 'contrasena' => 'hashed'];
    }

    public function getAuthPassword(): string
    {
        return $this->contrasena;
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'usuario_roles')->withPivot(['asignado_por_usuario_id', 'asignado_at'])->withTimestamps();
    }

    public function scopeActivo(Builder $query): void
    {
        $query->where('activo', true);
    }
}
