<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Miembro;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Miembro::class) ?? false;
    }

    public function rules(): array
    {
        return ['iglesia_id' => ['required', 'integer', 'exists:iglesias,id'], 'tipo_documento' => ['required', 'string', 'max:30'], 'numero_documento' => ['required', 'string', 'max:30'], 'nombres' => ['required', 'string', 'max:120'], 'apellidos' => ['required', 'string', 'max:120'], 'fecha_nacimiento' => ['nullable', 'date'], 'sexo' => ['nullable', 'in:M,F,O'], 'correo_electronico' => ['nullable', 'email', 'max:150'], 'telefono' => ['nullable', 'string', 'max:30'], 'direccion' => ['nullable', 'string', 'max:255']];
    }
}
