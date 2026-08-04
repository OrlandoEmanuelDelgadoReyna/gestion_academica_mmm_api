<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rol = $this->route('rol');

        return ['codigo' => ['sometimes', 'string', 'max:60', Rule::unique('roles', 'codigo')->ignore($rol)], 'nombre' => ['sometimes', 'string', 'max:100', Rule::unique('roles', 'nombre')->ignore($rol)], 'descripcion' => ['nullable', 'string', 'max:255'], 'activo' => ['sometimes', 'boolean']];
    }
}
