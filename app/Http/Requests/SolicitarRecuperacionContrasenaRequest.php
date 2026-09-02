<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SolicitarRecuperacionContrasenaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('correo_electronico');
        if (is_string($email)) {
            $this->merge(['correo_electronico' => strtolower(trim($email))]);
        }
    }

    public function rules(): array
    {
        return [
            'correo_electronico' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}
