<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCriterioEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('criterio_evaluacion')) ?? false;
    }

    public function rules(): array
    {
        $criterio = $this->route('criterio_evaluacion');

        return [
            'codigo' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('criterios_evaluacion', 'codigo')
                    ->where('programacion_academica_id', $criterio->programacion_academica_id)
                    ->ignore($criterio->id),
            ],
            'origen' => [
                'sometimes',
                'string',
                'in:tareas,examen_final',
                Rule::unique('criterios_evaluacion', 'origen')
                    ->where('programacion_academica_id', $criterio->programacion_academica_id)
                    ->ignore($criterio->id),
            ],
            'nombre' => ['sometimes', 'string', 'max:100'],
            'porcentaje' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'orden' => [
                'sometimes',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('criterios_evaluacion', 'orden')
                    ->where('programacion_academica_id', $criterio->programacion_academica_id)
                    ->ignore($criterio->id),
            ],
        ];
    }
}
