<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Attendance record for an enrolled member in a session. */
class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $fillable = ['sesion_id', 'matricula_id', 'estado', 'observacion', 'registrado_por_usuario_id'];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'registrado_por_usuario_id');
    }
}
