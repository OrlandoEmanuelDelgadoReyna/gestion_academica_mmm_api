<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('evento')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'inicio_at' => ['sometimes', 'date'],
            'fin_at' => ['sometimes', 'date', 'after:inicio_at'],
            'lugar' => ['nullable', 'string', 'max:150'],
            'estado' => ['sometimes', 'string', 'max:30'],
        ];
    }
}
