<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('tarea')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'publicado_at' => ['sometimes', 'date'],
            'fecha_limite_at' => ['nullable', 'date'],
            'puntaje_maximo' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tarea = $this->route('tarea');
            $publicado = $this->has('publicado_at') ? $this->input('publicado_at') : $tarea?->publicado_at;
            if ($this->exists('fecha_limite_at') && $this->input('fecha_limite_at') === null) {
                return;
            }
            $limite = $this->has('fecha_limite_at') ? $this->input('fecha_limite_at') : $tarea?->fecha_limite_at;
            if ($publicado === null || $limite === null) {
                return;
            }
            if (strtotime((string) $limite) <= strtotime((string) $publicado)) {
                $validator->errors()->add('fecha_limite_at', 'La fecha límite debe ser posterior a la fecha de publicación.');
            }
        });
    }
}
