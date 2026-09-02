<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamenFinal;
use Illuminate\Foundation\Http\FormRequest;

final class StorePreguntaExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examen = $this->route('examen_final') ?? $this->route('examenFinal');

        return $examen instanceof ExamenFinal
            && ($this->user()?->can('manageQuestions', $examen) ?? false);
    }

    public function rules(): array
    {
        return [
            'enunciado' => ['required', 'string'],
            'puntaje' => ['required', 'numeric', 'gt:0'],
            'orden' => ['sometimes', 'integer', 'min:1'],
            'tipo' => ['sometimes', 'string', 'in:seleccion_unica'],
            'activo' => ['sometimes', 'boolean'],
            'opciones' => ['required', 'array', 'min:1'],
            'opciones.*.texto' => ['required', 'string', 'max:500'],
            'opciones.*.es_correcta' => ['required', 'boolean'],
            'opciones.*.orden' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
