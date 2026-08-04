<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable audit entry for a domain action. */
class Auditoria extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'auditorias';

    protected $fillable = ['usuario_id', 'accion', 'tabla_afectada', 'registro_id', 'datos_antes', 'datos_despues', 'direccion_ip', 'dispositivo'];

    protected function casts(): array
    {
        return ['datos_antes' => 'array', 'datos_despues' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
