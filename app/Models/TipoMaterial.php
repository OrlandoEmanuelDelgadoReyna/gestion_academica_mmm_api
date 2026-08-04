<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Catalog classification for educational resources. */
class TipoMaterial extends Model
{
    use HasFactory;

    protected $table = 'tipos_material';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class);
    }
}
