<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Enrollment of a member in a concrete academic program. */
class Matricula extends Model
{
    use HasFactory;

    protected $table = 'matriculas';

    protected $fillable = ['programacion_academica_id', 'miembro_id', 'fecha_matricula', 'estado'];

    protected function casts(): array
    {
        return ['fecha_matricula' => 'datetime'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function entregasTarea(): HasMany
    {
        return $this->hasMany(EntregaTarea::class);
    }

    public function intentosExamen(): HasMany
    {
        return $this->hasMany(IntentoExamen::class);
    }

    public function calificacion(): HasOne
    {
        return $this->hasOne(Calificacion::class);
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('estado', 'activa');
    }
}
