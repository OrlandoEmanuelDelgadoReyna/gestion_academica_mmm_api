<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Notification message and its dispatch metadata. */
class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = ['iglesia_id', 'titulo', 'contenido', 'tipo', 'enviado_at', 'creado_por_usuario_id'];

    protected function casts(): array
    {
        return ['enviado_at' => 'datetime'];
    }

    public function iglesia(): BelongsTo
    {
        return $this->belongsTo(Iglesia::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por_usuario_id');
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(NotificacionDestinatario::class);
    }
}
