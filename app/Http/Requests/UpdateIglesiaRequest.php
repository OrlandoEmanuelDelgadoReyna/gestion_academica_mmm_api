<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIglesiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('iglesia')) ?? false;
    }

    public function rules(): array
    {
        return ['codigo' => ['sometimes', 'string', 'max:30', Rule::unique('iglesias', 'codigo')->ignore($this->route('iglesia'))], 'nombre' => ['sometimes', 'string', 'max:150'], 'direccion' => ['nullable', 'string', 'max:255'], 'telefono' => ['nullable', 'string', 'max:30'], 'correo_electronico' => ['nullable', 'email', 'max:150'], 'activo' => ['sometimes', 'boolean']];
    }
}
