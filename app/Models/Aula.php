<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Physical or virtual classroom belonging to a church. */
class Aula extends Model
{
    use HasFactory;

    protected $table = 'aulas';

    protected $fillable = ['iglesia_id', 'codigo', 'nombre', 'capacidad', 'activo'];

    protected function casts(): array
    {
        return ['capacidad' => 'integer', 'activo' => 'boolean'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function programaciones(): HasMany
    {
        return $this->hasMany(ProgramacionAcademica::class);
    }

    public function scopeActiva(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
