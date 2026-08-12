<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A scheduled delivery of a course. */
class ProgramacionAcademica extends Model
{
    use HasFactory;

    protected $table = 'programaciones_academicas';

    protected $fillable = ['curso_id', 'aula_id', 'periodo', 'grupo', 'fecha_inicio', 'fecha_fin', 'capacidad', 'escala_maxima', 'nota_minima_aprobatoria', 'maximo_intentos_examen', 'estado'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'capacidad' => 'integer', 'escala_maxima' => 'decimal:2', 'nota_minima_aprobatoria' => 'decimal:2', 'maximo_intentos_examen' => 'integer'];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function aula(): BelongsTo
    {
        return $this->belongsTo(Aula::class);
    }

    public function docentes(): BelongsToMany
    {
        return $this->belongsToMany(Miembro::class, 'programacion_docentes')->withTimestamps();
    }

    public function estadosMembresiaPermitidos(): BelongsToMany
    {
        return $this->belongsToMany(EstadoMembresia::class, 'programacion_estados_membresia_permitidos')->withTimestamps();
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(ProgramacionHorario::class);
    }

    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }

    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class);
    }

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class);
    }

    public function criteriosEvaluacion(): HasMany
    {
        return $this->hasMany(CriterioEvaluacion::class);
    }

    public function examenFinal(): HasOne
    {
        return $this->hasOne(ExamenFinal::class);
    }

    public function scopeAbierta(Builder $query): Builder
    {
        return $query->where('estado', 'abierta');
    }
}
