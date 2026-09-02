<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\PreguntaExamen;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePreguntaExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pregunta = $this->route('pregunta_examen') ?? $this->route('preguntaExamen');
        if (! $pregunta instanceof PreguntaExamen) {
            return false;
        }
        $pregunta->loadMissing('examenFinal');

        return $this->user()?->can('manageQuestions', $pregunta->examenFinal) ?? false;
    }

    public function rules(): array
    {
        return [
            'enunciado' => ['sometimes', 'string'],
            'puntaje' => ['sometimes', 'numeric', 'gt:0'],
            'orden' => ['sometimes', 'integer', 'min:1'],
            'tipo' => ['sometimes', 'string', 'in:seleccion_unica'],
            'activo' => ['sometimes', 'boolean'],
            'opciones' => ['sometimes', 'array', 'min:1'],
            'opciones.*.texto' => ['required_with:opciones', 'string', 'max:500'],
            'opciones.*.es_correcta' => ['required_with:opciones', 'boolean'],
            'opciones.*.orden' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
