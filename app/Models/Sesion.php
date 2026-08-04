<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A dated meeting within an academic program. */
class Sesion extends Model
{
    use HasFactory;

    protected $table = 'sesiones';

    protected $fillable = ['programacion_academica_id', 'orden', 'inicio_at', 'fin_at', 'tema', 'estado'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'inicio_at' => 'datetime', 'fin_at' => 'datetime'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function lecciones(): BelongsToMany
    {
        return $this->belongsToMany(Leccion::class, 'sesion_lecciones')->withTimestamps();
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }
}
