<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Learning resource published for an academic program. */
class Material extends Model
{
    use HasFactory;

    protected $table = 'materiales';

    protected $fillable = ['programacion_academica_id', 'tipo_material_id', 'titulo', 'descripcion', 'ruta_recurso', 'publicado_at', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['publicado_at' => 'datetime'];
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function tipoMaterial(): BelongsTo
    {
        return $this->belongsTo(TipoMaterial::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }
}
