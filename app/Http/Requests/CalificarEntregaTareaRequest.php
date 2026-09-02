<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EntregaTarea;
use Illuminate\Foundation\Http\FormRequest;

final class CalificarEntregaTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entrega = $this->route('entregaTarea') ?? $this->route('entrega_tarea');

        return $this->user()?->can('grade', $entrega) ?? false;
    }

    public function rules(): array
    {
        $max = $this->puntajeMaximo();

        return [
            'nota' => ['required', 'numeric', 'min:0', 'max:'.$max],
            'retroalimentacion' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'nota.required' => 'La nota es obligatoria.',
            'nota.numeric' => 'La nota debe ser un número.',
            'nota.min' => 'La nota no puede ser negativa.',
            'nota.max' => 'La nota no puede superar el puntaje máximo de la tarea.',
        ];
    }

    private function puntajeMaximo(): string
    {
        $entrega = $this->route('entregaTarea') ?? $this->route('entrega_tarea');
        if (! $entrega instanceof EntregaTarea) {
            return '0';
        }

        $entrega->loadMissing('tarea');
        $max = $entrega->tarea?->puntaje_maximo;

        return $max === null ? '0' : (string) $max;
    }
}
