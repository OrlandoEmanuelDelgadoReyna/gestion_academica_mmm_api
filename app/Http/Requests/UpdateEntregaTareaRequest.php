<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateEntregaTareaRequest extends FormRequest
{
    use ValidatesEntregaArchivo;

    public function authorize(): bool
    {
        $entrega = $this->route('entregaTarea') ?? $this->route('entrega_tarea');

        return $this->user()?->can('update', $entrega) ?? false;
    }

    public function rules(): array
    {
        return [
            'contenido' => ['nullable', 'string'],
            'archivo' => $this->archivoRules(),
            'eliminar_archivo' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $v) => $this->afterValidatingEntregaArchivo($v));
    }
}
