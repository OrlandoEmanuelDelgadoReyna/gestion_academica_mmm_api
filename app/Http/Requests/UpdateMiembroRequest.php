<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('miembro')) ?? false;
    }

    public function rules(): array
    {
        return ['iglesia_id' => ['sometimes', 'integer', 'exists:iglesias,id'], 'tipo_documento' => ['sometimes', 'string', 'max:30'], 'numero_documento' => ['sometimes', 'string', 'max:30'], 'nombres' => ['sometimes', 'string', 'max:120'], 'apellidos' => ['sometimes', 'string', 'max:120'], 'fecha_nacimiento' => ['nullable', 'date'], 'sexo' => ['nullable', 'in:M,F,O'], 'correo_electronico' => ['nullable', 'email', 'max:150'], 'telefono' => ['nullable', 'string', 'max:30'], 'direccion' => ['nullable', 'string', 'max:255']];
    }
}
