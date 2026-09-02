<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateExamenFinalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $examen = $this->route('examenFinal') ?? $this->route('examen_final');

        return $this->user()?->can('update', $examen) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'inicio_at' => ['nullable', 'date'],
            'fin_at' => ['nullable', 'date'],
            'puntaje_maximo' => ['sometimes', 'numeric', 'gt:0'],
            'nota_minima_aprobatoria' => ['sometimes', 'numeric', 'min:0'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $examen = $this->route('examenFinal') ?? $this->route('examen_final');
            $max = $this->input('puntaje_maximo', $examen?->puntaje_maximo);
            $min = $this->input('nota_minima_aprobatoria', $examen?->nota_minima_aprobatoria);
            if ($min !== null && $max !== null && (float) $min > (float) $max) {
                $validator->errors()->add('nota_minima_aprobatoria', 'La nota mínima no puede superar el puntaje máximo.');
            }
            $inicio = $this->input('inicio_at', $examen?->inicio_at);
            $fin = $this->input('fin_at', $examen?->fin_at);
            if ($inicio !== null && $fin !== null && strtotime((string) $fin) <= strtotime((string) $inicio)) {
                $validator->errors()->add('fin_at', 'La fecha de fin debe ser posterior al inicio.');
            }
        });
    }
}
