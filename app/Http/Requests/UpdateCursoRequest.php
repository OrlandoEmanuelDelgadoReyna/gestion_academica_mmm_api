<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('curso')) ?? false;
    }

    public function rules(): array
    {
        $curso = $this->route('curso');

        return [
            'codigo' => ['sometimes', 'string', 'max:60', Rule::unique('cursos', 'codigo')->where('iglesia_id', $curso->iglesia_id)->ignore($curso->id)],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
