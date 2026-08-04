<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registered push-notification device for a user. */
class DispositivoNotificacion extends Model
{
    use HasFactory;

    protected $table = 'dispositivos_notificacion';

    protected $fillable = ['usuario_id', 'token_push', 'plataforma', 'nombre_dispositivo', 'activo', 'ultimo_uso_at'];

    protected $hidden = ['token_push'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'ultimo_uso_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
