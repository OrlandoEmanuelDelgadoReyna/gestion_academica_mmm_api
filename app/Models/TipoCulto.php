<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catalog of worship service types. */
class TipoCulto extends Model
{
    use HasFactory;

    protected $table = 'tipos_culto';

    protected $fillable = ['iglesia_id', 'codigo', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function cultos(): HasMany
    {
        return $this->hasMany(Culto::class);
    }
}
