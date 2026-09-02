<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Student request to retake a failed final exam. */
class SolicitudRecuperacionExamen extends Model
{
    use HasFactory;

    public const PENDIENTE = 'pendiente';

    public const APROBADA = 'aprobada';

    public const RECHAZADA = 'rechazada';

    public const REALIZADA = 'realizada';

    /** @var list<string> */
    public const ESTADOS = [self::PENDIENTE, self::APROBADA, self::RECHAZADA, self::REALIZADA];

    /** @var list<string> */
    public const ACTIVAS = [self::PENDIENTE, self::APROBADA];

    protected $table = 'solicitudes_recuperacion_examen';

    protected $fillable = [
        'examen_final_id',
        'matricula_id',
        'estado',
        'solicitada_at',
        'atendida_por_usuario_id',
        'atendida_at',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'solicitada_at' => 'datetime',
            'atendida_at' => 'datetime',
        ];
    }

    public function examenFinal(): BelongsTo
    {
        return $this->belongsTo(ExamenFinal::class);
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class);
    }

    public function atendidaPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'atendida_por_usuario_id');
    }

    public function isActiva(): bool
    {
        return in_array($this->estado, self::ACTIVAS, true);
    }
}
