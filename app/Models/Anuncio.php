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
        $now = now();

        return $query->publicado()
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('publicado_at')->orWhere('publicado_at', '<=', $now);
            })
            ->where(function (Builder $builder) use ($now): void {
                $builder->whereNull('vence_at')->orWhere('vence_at', '>=', $now);
            });
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

        if ($this->publicado_at !== null && $this->publicado_at->gt(now())) {
            return false;
        }

        if ($this->vence_at !== null && $this->vence_at->lt(now())) {
            return false;
        }

        return true;
    }
}
