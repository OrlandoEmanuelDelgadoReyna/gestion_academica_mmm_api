<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Final exam configured for a program. */
class ExamenFinal extends Model
{
    use HasFactory;

    protected $table = 'examenes_finales';

    protected $fillable = ['programacion_academica_id', 'titulo', 'descripcion', 'inicio_at', 'fin_at', 'puntaje_maximo', 'nota_minima_aprobatoria', 'activo', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['inicio_at' => 'datetime', 'fin_at' => 'datetime', 'puntaje_maximo' => 'decimal:2', 'nota_minima_aprobatoria' => 'decimal:2', 'activo' => 'boolean'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function preguntas(): HasMany
    {
        return $this->hasMany(PreguntaExamen::class);
    }

    public function intentos(): HasMany
    {
        return $this->hasMany(IntentoExamen::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaExamenFinal::class);
    }

    public function solicitudesRecuperacion(): HasMany
    {
        return $this->hasMany(SolicitudRecuperacionExamen::class);
    }
}
