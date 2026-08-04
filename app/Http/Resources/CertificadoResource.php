<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Certificado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Certificado */
final class CertificadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'miembro_id' => $this->miembro_id,
            'tipo_certificado_id' => $this->tipo_certificado_id,
            'programacion_academica_id' => $this->programacion_academica_id,
            'certificado_reemplazado_id' => $this->certificado_reemplazado_id,
            'codigo_verificacion' => $this->codigo_verificacion,
            'emitido_at' => $this->emitido_at,
            'estado' => $this->estado,
            'destinatario' => $this->destinatario,
            'motivo' => $this->when($this->estado === 'revocado', $this->motivo),
            'vence_at' => $this->vence_at,
            'ruta_documento' => $this->ruta_documento,
            'miembro' => new MiembroResource($this->whenLoaded('miembro')),
            'tipo_certificado' => $this->whenLoaded('tipoCertificado'),
            'programacion_academica' => $this->whenLoaded('programacionAcademica'),
        ];
    }
}
