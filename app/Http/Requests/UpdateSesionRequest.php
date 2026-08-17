<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('sesion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['prohibited'],
            'orden' => ['sometimes', 'integer', 'min:1'],
            'inicio_at' => ['sometimes', 'date'],
            'fin_at' => ['sometimes', 'date'],
            'tema' => ['nullable', 'string', 'max:255'],
            'estado' => ['sometimes', 'string', Rule::in(['programada', 'realizada', 'cancelada'])],
            'leccion_ids' => ['sometimes', 'array'],
            'leccion_ids.*' => ['integer', 'exists:lecciones,id'],
        ];
    }
}
