<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

/** Issued document with lifecycle controls for revocation or replacement. */
class Certificado extends Model
{
    use HasFactory;

    protected $table = 'certificados';

    public const ESTADO_EMITIDO = 'emitido';

    public const ESTADO_REVOCADO = 'revocado';

    public const ESTADO_REEMPLAZADO = 'reemplazado';

    protected $fillable = ['miembro_id', 'tipo_certificado_id', 'programacion_academica_id', 'certificado_reemplazado_id', 'codigo_verificacion', 'emitido_at', 'estado', 'destinatario', 'motivo', 'vence_at', 'ruta_documento', 'firmado_por_miembro_id', 'firmado_at', 'autorizado_por_miembro_id', 'autorizado_at', 'emitido_por_usuario_id'];

    protected function casts(): array
    {
        return ['emitido_at' => 'datetime', 'vence_at' => 'datetime', 'firmado_at' => 'datetime', 'autorizado_at' => 'datetime'];
    }

    public function tipoCertificado(): BelongsTo
    {
        return $this->belongsTo(TipoCertificado::class);
    }

    public function miembro(): BelongsTo
    {
        return $this->belongsTo(Miembro::class);
    }

    public function programacionAcademica(): BelongsTo
    {
        return $this->belongsTo(ProgramacionAcademica::class);
    }

    public function emitidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'emitido_por_usuario_id');
    }

    public function firmadoPor(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'firmado_por_miembro_id');
    }

    public function autorizadoPor(): BelongsTo
    {
        return $this->belongsTo(Miembro::class, 'autorizado_por_miembro_id');
    }

    public function reemplaza(): BelongsTo
    {
        return $this->belongsTo(self::class, 'certificado_reemplazado_id');
    }

    public function reemplazos(): HasMany
    {
        return $this->hasMany(self::class, 'certificado_reemplazado_id');
    }

    protected function codigoLegible(): Attribute
    {
        return Attribute::get(function (): string {
            $year = $this->emitido_at?->format('Y') ?? now()->format('Y');
            $compact = strtoupper(str_replace('-', '', (string) $this->codigo_verificacion));
            $suffix = substr($compact, -6) ?: '000000';

            return 'CERT-'.$year.'-'.$suffix;
        });
    }

    public function esValidoPublicamente(): bool
    {
        if ($this->estado !== self::ESTADO_EMITIDO) {
            return false;
        }

        return $this->vence_at === null || now()->lte($this->vence_at);
    }

    public function estadoValidacionPublica(): string
    {
        if ($this->estado === self::ESTADO_REVOCADO) {
            return 'revocado';
        }

        if ($this->estado === self::ESTADO_REEMPLAZADO) {
            return 'reemplazado';
        }

        if ($this->vence_at !== null && now()->gt($this->vence_at)) {
            return 'vencido';
        }

        return $this->estado === self::ESTADO_EMITIDO ? 'valido' : 'invalido';
    }
}
