<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tarea')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'publicado_at' => ['sometimes', 'date'],
            'fecha_limite_at' => ['nullable', 'date'],
            'puntaje_maximo' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }
}
