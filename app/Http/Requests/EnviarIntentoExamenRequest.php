<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EnviarIntentoExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('intento_examen')) ?? false;
    }

    public function rules(): array
    {
        return [
            'respuestas' => ['required', 'array', 'min:1'],
            'respuestas.*.pregunta_examen_id' => ['required', 'integer', 'exists:preguntas_examen,id'],
            'respuestas.*.opcion_pregunta_id' => ['nullable', 'integer', 'exists:opciones_pregunta,id'],
            'respuestas.*.respuesta_texto' => ['nullable', 'string'],
        ];
    }
}
