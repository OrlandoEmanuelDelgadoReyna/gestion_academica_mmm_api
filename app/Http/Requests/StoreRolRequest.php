<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['codigo' => ['required', 'string', 'max:60', 'unique:roles,codigo'], 'nombre' => ['required', 'string', 'max:100', 'unique:roles,nombre'], 'descripcion' => ['nullable', 'string', 'max:255'], 'activo' => ['sometimes', 'boolean'], 'permisos' => ['array'], 'permisos.*' => ['integer', 'exists:permisos,id']];
    }
}
