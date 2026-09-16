<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\AnuncioVigencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Public church announcement with an optional publication window. */
class Anuncio extends Model
{
    use HasFactory;

    public const BORRADOR = 'borrador';

    public const PUBLICADO = 'publicado';

    public const ARCHIVADO = 'archivado';

    /** @var list<string> */
    public const ESTADOS = [self::BORRADOR, self::PUBLICADO, self::ARCHIVADO];

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
        return $query->where('estado', self::PUBLICADO);
    }

    public function scopeVigente(Builder $query): Builder
    {
        return AnuncioVigencia::constrainVigente($query->publicado());
    }

    public function isPublicado(): bool
    {
        return $this->estado === self::PUBLICADO;
    }

    public function isVigente(): bool
    {
        if (! $this->isPublicado()) {
            return false;
        }

        return AnuncioVigencia::isOpen($this->publicado_at, $this->vence_at);
    }
}
