<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Question in a final exam. */
class PreguntaExamen extends Model
{
    use HasFactory;

    protected $table = 'preguntas_examen';

    protected $fillable = ['examen_final_id', 'orden', 'tipo', 'enunciado', 'puntaje'];

    protected function casts(): array
    {
        return ['puntaje' => 'decimal:2', 'orden' => 'integer'];
    }

    public function examenFinal(): BelongsTo
    {
        return $this->belongsTo(ExamenFinal::class);
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(OpcionPregunta::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaExamen::class);
    }
}
