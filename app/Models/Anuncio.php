<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Public church announcement with an optional publication window. */
class Anuncio extends Model
{
    use HasFactory;

    protected $table = 'anuncios';

    protected $fillable = ['iglesia_id', 'titulo', 'contenido', 'estado', 'publicado_at', 'vence_at', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['publicado_at' => 'datetime', 'vence_at' => 'datetime'];
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
