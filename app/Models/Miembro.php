<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Miembro extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['iglesia_id', 'tipo_documento', 'numero_documento', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'correo_electronico', 'telefono', 'direccion'];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    protected $appends = ['nombre_completo'];

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->nombres} {$this->apellidos}");
    }

    public function scopeDeIglesia(Builder $query, int $iglesiaId): void
    {
        $query->where('iglesia_id', $iglesiaId);
    }
}
