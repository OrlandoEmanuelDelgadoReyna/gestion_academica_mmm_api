<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Iglesia extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['codigo', 'nombre', 'direccion', 'telefono', 'correo_electronico', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function miembros(): HasMany
    {
        return $this->hasMany(Miembro::class);
    }

    public function scopeActiva(Builder $query): void
    {
        $query->where('activo', true);
    }
}
