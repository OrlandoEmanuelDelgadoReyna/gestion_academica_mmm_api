<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePermisoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['codigo' => ['sometimes', 'string', 'max:100', Rule::unique('permisos', 'codigo')->ignore($this->route('permiso'))], 'modulo' => ['sometimes', 'string', 'max:60'], 'nombre' => ['sometimes', 'string', 'max:120'], 'descripcion' => ['nullable', 'string', 'max:255'], 'activo' => ['sometimes', 'boolean']];
    }
}
