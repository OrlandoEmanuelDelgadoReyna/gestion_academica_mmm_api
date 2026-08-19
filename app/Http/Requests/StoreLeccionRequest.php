<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Leccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreLeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Leccion::class) ?? false;
    }

    public function rules(): array
    {
        $cursoId = $this->integer('curso_id');

        return [
            'curso_id' => ['required', 'integer', 'exists:cursos,id'],
            'orden' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('lecciones', 'orden')->where('curso_id', $cursoId),
            ],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
