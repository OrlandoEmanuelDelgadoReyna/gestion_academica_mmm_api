<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Curso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Curso::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'codigo' => ['required', 'string', 'max:60', Rule::unique('cursos', 'codigo')->where('iglesia_id', $this->integer('iglesia_id'))],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
