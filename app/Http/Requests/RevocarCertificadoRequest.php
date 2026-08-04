<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RevocarCertificadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('revocar', $this->route('certificado')) ?? false;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string'],
        ];
    }
}
