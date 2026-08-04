<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A scheduled worship service. */
class Culto extends Model
{
    use HasFactory;

    protected $table = 'cultos';

    protected $fillable = ['iglesia_id', 'tipo_culto_id', 'inicio_at', 'fin_at', 'lugar', 'estado', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['inicio_at' => 'datetime', 'fin_at' => 'datetime'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function tipoCulto(): BelongsTo
    {
        return $this->belongsTo(TipoCulto::class);
    }

    public function bloques(): HasMany
    {
        return $this->hasMany(BloqueCulto::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }
}
