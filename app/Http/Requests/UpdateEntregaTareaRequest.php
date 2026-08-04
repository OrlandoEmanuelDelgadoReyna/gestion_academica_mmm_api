<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateEntregaTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('entrega_tarea')) ?? false;
    }

    public function rules(): array
    {
        return [
            'contenido' => ['nullable', 'string'],
            'ruta_archivo' => ['nullable', 'string', 'max:2048'],
            'nota' => ['nullable', 'numeric', 'min:0'],
            'retroalimentacion' => ['nullable', 'string'],
            'calificado_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('nota')) {
            $this->merge([
                'calificado_por_usuario_id' => $this->user()?->id,
                'calificado_at' => $this->input('calificado_at', now()->toDateTimeString()),
            ]);
        }
    }
}
