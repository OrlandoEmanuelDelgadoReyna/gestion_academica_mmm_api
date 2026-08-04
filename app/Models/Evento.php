<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Calendar event published by a church. */
class Evento extends Model
{
    use HasFactory;

    protected $table = 'eventos';

    protected $fillable = ['iglesia_id', 'titulo', 'descripcion', 'inicio_at', 'fin_at', 'lugar', 'estado', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['inicio_at' => 'datetime', 'fin_at' => 'datetime'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }

    public function scopePublicado(Builder $query): Builder
    {
        return $query->where('estado', 'publicado');
    }
}
