<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Assessable assignment configured for an academic program. */
class Tarea extends Model
{
    use HasFactory;

    protected $table = 'tareas';

    protected $fillable = ['programacion_academica_id', 'titulo', 'descripcion', 'publicado_at', 'fecha_limite_at', 'puntaje_maximo', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['publicado_at' => 'datetime', 'fecha_limite_at' => 'datetime', 'puntaje_maximo' => 'decimal:2'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(EntregaTarea::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }
}
