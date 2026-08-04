<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-user delivery state of a notification. */
class NotificacionDestinatario extends Model
{
    use HasFactory;

    protected $table = 'notificacion_destinatarios';

    protected $fillable = ['notificacion_id', 'usuario_id', 'estado', 'entregado_at', 'leido_at'];

    protected function casts(): array
    {
        return ['entregado_at' => 'datetime', 'leido_at' => 'datetime'];
    }

    public function notificacion(): BelongsTo
    {
        return $this->belongsTo(Notificacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
