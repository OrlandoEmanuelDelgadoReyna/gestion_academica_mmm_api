<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Link between a program session and its delivered lesson. */
class SesionLeccion extends Model
{
    protected $table = 'sesion_lecciones';

    protected $fillable = ['sesion_id', 'leccion_id'];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    public function leccion(): BelongsTo
    {
        return $this->belongsTo(Leccion::class);
    }
}
