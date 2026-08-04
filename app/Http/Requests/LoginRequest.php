<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['nombre_usuario' => ['required', 'string', 'max:60'], 'contrasena' => ['required', 'string'], 'nombre_dispositivo' => ['nullable', 'string', 'max:120']];
    }
}
