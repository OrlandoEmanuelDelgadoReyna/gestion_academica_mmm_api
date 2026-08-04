<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\CriterioEvaluacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCriterioEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CriterioEvaluacion::class) ?? false;
    }

    public function rules(): array
    {
        $programacionId = $this->integer('programacion_academica_id');

        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'codigo' => [
                'required',
                'string',
                'max:30',
                Rule::unique('criterios_evaluacion', 'codigo')->where('programacion_academica_id', $programacionId),
            ],
            'origen' => [
                'required',
                'string',
                'in:tareas,examen_final',
                Rule::unique('criterios_evaluacion', 'origen')->where('programacion_academica_id', $programacionId),
            ],
            'nombre' => ['required', 'string', 'max:100'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
            'orden' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('criterios_evaluacion', 'orden')->where('programacion_academica_id', $programacionId),
            ],
        ];
    }
}
