<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Configurable certificate or recommendation-letter type. */
class TipoCertificado extends Model
{
    use HasFactory;

    protected $table = 'tipos_certificado';

    protected $fillable = ['codigo', 'nombre', 'categoria', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function certificados(): HasMany
    {
        return $this->hasMany(Certificado::class);
    }
}
