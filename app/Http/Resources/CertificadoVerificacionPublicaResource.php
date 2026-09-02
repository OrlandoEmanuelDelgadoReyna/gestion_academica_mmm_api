<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Certificado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Certificado */
final class CertificadoVerificacionPublicaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $miembro = $this->miembro;
        $programacion = $this->programacionAcademica;
        $iglesia = $programacion?->curso?->iglesia ?? $miembro?->iglesia;

        return [
            'nombre' => $this->destinatario ?: ($miembro?->nombre_completo ?: null),
            'curso' => $programacion?->curso?->nombre,
            'institucion' => $iglesia?->nombre,
            'fecha_emision' => $this->emitido_at,
            'codigo' => $this->codigo_verificacion,
            'codigo_legible' => $this->codigo_legible,
            'estado' => $this->estado,
            'estado_validacion' => $this->estadoValidacionPublica(),
        ];
    }
}
