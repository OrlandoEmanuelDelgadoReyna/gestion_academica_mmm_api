<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Assignment of a member to a worship-service participation. */
class ParticipacionCulto extends Model
{
    use HasFactory;

    protected $table = 'participaciones_culto';

    protected $fillable = ['bloque_culto_id', 'miembro_id', 'estado', 'observacion'];

    public function bloqueCulto(): BelongsTo
    {
        return $this->belongsTo(BloqueCulto::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }
}
