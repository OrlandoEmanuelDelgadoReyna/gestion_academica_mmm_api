<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Certificado;
use Illuminate\Foundation\Http\FormRequest;

final class EmitirCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('emitir', Certificado::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'miembro_id' => ['required', 'integer', 'exists:miembros,id'],
            'tipo_certificado_id' => ['required', 'integer', 'exists:tipos_certificado,id'],
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'destinatario' => ['nullable', 'string', 'max:150'],
            'vence_at' => ['nullable', 'date'],
            'ruta_documento' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
