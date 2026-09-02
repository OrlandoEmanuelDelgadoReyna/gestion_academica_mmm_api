<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamenFinal;
use Illuminate\Foundation\Http\FormRequest;

final class RegistrarNotaExamenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examen = $this->route('examen_final') ?? $this->route('examenFinal');

        return $examen instanceof ExamenFinal
            && ($this->user()?->can('grade', $examen) ?? false);
    }

    public function rules(): array
    {
        $examen = $this->route('examen_final') ?? $this->route('examenFinal');
        $max = $examen instanceof ExamenFinal ? (string) $examen->puntaje_maximo : '0';

        return [
            'nota' => ['required', 'numeric', 'min:0', 'max:'.$max],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.required' => 'La nota es obligatoria.',
            'nota.numeric' => 'La nota debe ser un número.',
            'nota.min' => 'La nota no puede ser negativa.',
            'nota.max' => 'La nota no puede superar el puntaje máximo del examen.',
        ];
    }
}
