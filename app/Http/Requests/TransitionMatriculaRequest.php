<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('matricula')) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(['activa', 'retirada'])],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.in' => 'La matrícula completada se asigna automáticamente. Solo se puede gestionar activa o retirada.',
        ];
    }
}
