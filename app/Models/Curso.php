<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/** Reusable curricular definition for biblical education. */
class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = ['iglesia_id', 'codigo', 'nombre', 'descripcion', 'portada_path', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class);
    }

    public function matriculas(): HasManyThrough
    {
        return $this->hasManyThrough(
            Matricula::class,
            ProgramacionAcademica::class,
            'curso_id',
            'programacion_academica_id',
        );
    }

    public function lecciones(): HasMany
    {
        return $this->hasMany(Leccion::class)->orderBy('orden');
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
