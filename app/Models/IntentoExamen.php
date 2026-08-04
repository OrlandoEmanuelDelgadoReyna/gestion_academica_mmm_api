<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One attempt by an enrolled member to complete a final exam. */
class IntentoExamen extends Model
{
    use HasFactory;

    protected $table = 'intentos_examen';

    protected $fillable = ['examen_final_id', 'matricula_id', 'inicio_at', 'fin_at', 'estado', 'puntaje_obtenido'];

    protected function casts(): array
    {
        return ['inicio_at' => 'datetime', 'fin_at' => 'datetime', 'puntaje_obtenido' => 'decimal:2'];
    }

    public function examenFinal(): BelongsTo
    {
        return $this->belongsTo(ExamenFinal::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function respuestas(): HasMany
    {
        return $this->hasMany(RespuestaExamen::class);
    }
}
