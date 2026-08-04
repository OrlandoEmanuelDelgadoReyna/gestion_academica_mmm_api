<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReemplazarCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reemplazar', $this->route('certificado')) ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_certificado_id' => ['sometimes', 'integer', 'exists:tipos_certificado,id'],
            'destinatario' => ['nullable', 'string', 'max:150'],
            'vence_at' => ['nullable', 'date'],
            'ruta_documento' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
