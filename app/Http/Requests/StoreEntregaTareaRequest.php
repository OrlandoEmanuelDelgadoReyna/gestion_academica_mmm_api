<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\EntregaTarea;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEntregaTareaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', EntregaTarea::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'tarea_id' => ['required', 'integer', 'exists:tareas,id'],
            'matricula_id' => ['required', 'integer', 'exists:matriculas,id'],
            'contenido' => ['nullable', 'string'],
            'ruta_archivo' => ['nullable', 'string', 'max:2048'],
            'entregado_at' => ['sometimes', 'date'],
        ];
    }
}
