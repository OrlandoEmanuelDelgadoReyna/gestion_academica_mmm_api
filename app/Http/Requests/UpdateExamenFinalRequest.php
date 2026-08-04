<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExamenFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('examen_final')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'inicio_at' => ['nullable', 'date'],
            'fin_at' => ['nullable', 'date'],
            'puntaje_maximo' => ['sometimes', 'numeric', 'gt:0'],
            'nota_minima_aprobatoria' => ['sometimes', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
