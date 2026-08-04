<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StorePermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['codigo' => ['required', 'string', 'max:100', 'unique:permisos,codigo'], 'modulo' => ['required', 'string', 'max:60'], 'nombre' => ['required', 'string', 'max:120'], 'descripcion' => ['nullable', 'string', 'max:255'], 'activo' => ['sometimes', 'boolean']];
    }
}
