<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Answer submitted for a particular exam question. */
class RespuestaExamen extends Model
{
    use HasFactory;

    protected $table = 'respuestas_examen';

    protected $fillable = ['intento_examen_id', 'pregunta_examen_id', 'opcion_pregunta_id', 'respuesta_texto', 'es_correcta', 'puntaje_obtenido'];

    protected function casts(): array
    {
        return ['es_correcta' => 'boolean', 'puntaje_obtenido' => 'decimal:2'];
    }

    public function intentoExamen(): BelongsTo
    {
        return $this->belongsTo(IntentoExamen::class);
    }

    public function preguntaExamen(): BelongsTo
    {
        return $this->belongsTo(PreguntaExamen::class);
    }

    public function opcionPregunta(): BelongsTo
    {
        return $this->belongsTo(OpcionPregunta::class);
    }
}
