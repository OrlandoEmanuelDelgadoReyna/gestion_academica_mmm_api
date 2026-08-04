<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Ordered element in a worship service agenda. */
class BloqueCulto extends Model
{
    use HasFactory;

    protected $table = 'bloques_culto';

    protected $fillable = ['culto_id', 'tipo_participacion_id', 'orden', 'contenido'];

    protected function casts(): array
    {
        return ['orden' => 'integer'];
    }

    public function culto(): BelongsTo
    {
        return $this->belongsTo(Culto::class);
    }

    public function tipoParticipacion(): BelongsTo
    {
        return $this->belongsTo(TipoParticipacion::class);
    }

    public function participaciones(): HasMany
    {
        return $this->hasMany(ParticipacionCulto::class);
    }
}
