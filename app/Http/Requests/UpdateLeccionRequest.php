<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('leccion')) ?? false;
    }

    public function rules(): array
    {
        $leccion = $this->route('leccion');

        return [
            'curso_id' => ['prohibited'],
            'orden' => [
                'sometimes',
                'integer',
                'min:1',
                Rule::unique('lecciones', 'orden')
                    ->where('curso_id', $leccion->curso_id)
                    ->ignore($leccion->id),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
