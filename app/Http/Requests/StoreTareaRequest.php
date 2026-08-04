<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tarea;
use Illuminate\Foundation\Http\FormRequest;

final class StoreTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Tarea::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'publicado_at' => ['required', 'date'],
            'fecha_limite_at' => ['nullable', 'date', 'after:publicado_at'],
            'puntaje_maximo' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['creado_por_usuario_id' => $this->user()?->id]);
    }
}
