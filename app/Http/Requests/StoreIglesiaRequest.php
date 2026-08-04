<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Iglesia;
use Illuminate\Foundation\Http\FormRequest;

final class StoreIglesiaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Iglesia::class) ?? false;
    }

    public function rules(): array
    {
        return ['codigo' => ['required', 'string', 'max:30', 'unique:iglesias,codigo'], 'nombre' => ['required', 'string', 'max:150'], 'direccion' => ['nullable', 'string', 'max:255'], 'telefono' => ['nullable', 'string', 'max:30'], 'correo_electronico' => ['nullable', 'email', 'max:150'], 'activo' => ['sometimes', 'boolean']];
    }
}
