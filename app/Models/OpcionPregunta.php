<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Selectable answer option for a closed question. */
class OpcionPregunta extends Model
{
    use HasFactory;

    protected $table = 'opciones_pregunta';

    protected $fillable = ['pregunta_examen_id', 'texto', 'es_correcta', 'orden'];

    protected function casts(): array
    {
        return ['es_correcta' => 'boolean', 'orden' => 'integer'];
    }

    public function preguntaExamen(): BelongsTo
    {
        return $this->belongsTo(PreguntaExamen::class);
    }
}
