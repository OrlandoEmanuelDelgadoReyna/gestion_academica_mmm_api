<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Ordered unit of content belonging to a course. */
class Leccion extends Model
{
    use HasFactory;

    protected $table = 'lecciones';

    protected $fillable = ['curso_id', 'orden', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activo' => 'boolean'];
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    public function sesiones(): BelongsToMany
    {
        return $this->belongsToMany(Sesion::class, 'sesion_lecciones')->withTimestamps();
    }
}
