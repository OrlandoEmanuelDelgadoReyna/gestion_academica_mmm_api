<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ExamenFinal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreExamenFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ExamenFinal::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => [
                'required',
                'integer',
                'exists:programaciones_academicas,id',
                Rule::unique('examenes_finales', 'programacion_academica_id'),
            ],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'inicio_at' => ['nullable', 'date'],
            'fin_at' => ['nullable', 'date', 'after:inicio_at'],
            'puntaje_maximo' => ['required', 'numeric', 'gt:0'],
            'nota_minima_aprobatoria' => ['required', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
