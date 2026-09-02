<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Certificado;
use App\Support\MaterialStorage;
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
            'codigo_legible' => $this->codigo_legible,
            'emitido_at' => $this->emitido_at,
            'estado' => $this->estado,
            'destinatario' => $this->destinatario,
            'motivo' => $this->when($this->estado === 'revocado', $this->motivo),
            'vence_at' => $this->vence_at,
            'tiene_documento' => MaterialStorage::exists($this->ruta_documento),
            'miembro' => $this->whenLoaded('miembro', fn () => [
                'id' => $this->miembro?->id,
                'nombres' => $this->miembro?->nombres,
                'apellidos' => $this->miembro?->apellidos,
                'nombre_completo' => $this->miembro?->nombre_completo,
                'tipo_documento' => $this->miembro?->tipo_documento,
                'numero_documento' => $this->miembro?->numero_documento,
            ]),
            'tipo_certificado' => $this->whenLoaded('tipoCertificado'),
            'programacion_academica' => $this->when(
                $this->relationLoaded('programacionAcademica') && $this->programacionAcademica !== null,
                fn () => [
                    'id' => $this->programacionAcademica->id,
                    'periodo' => $this->programacionAcademica->periodo,
                    'grupo' => $this->programacionAcademica->grupo,
                    'fecha_inicio' => $this->programacionAcademica->fecha_inicio?->toDateString(),
                    'fecha_fin' => $this->programacionAcademica->fecha_fin?->toDateString(),
                    'curso' => $this->programacionAcademica->relationLoaded('curso')
                        ? [
                            'id' => $this->programacionAcademica->curso?->id,
                            'codigo' => $this->programacionAcademica->curso?->codigo,
                            'nombre' => $this->programacionAcademica->curso?->nombre,
                        ]
                        : null,
                ],
            ),
        ];
    }
}
