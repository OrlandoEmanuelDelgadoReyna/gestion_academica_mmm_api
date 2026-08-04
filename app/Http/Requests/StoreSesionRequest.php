<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sesion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSesionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sesion::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'orden' => ['required', 'integer', 'min:1'],
            'inicio_at' => ['required', 'date'],
            'fin_at' => ['required', 'date'],
            'tema' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'string', Rule::in(['programada', 'realizada', 'cancelada'])],
            'leccion_ids' => ['sometimes', 'array'],
            'leccion_ids.*' => ['integer', 'exists:lecciones,id'],
        ];
    }
}
