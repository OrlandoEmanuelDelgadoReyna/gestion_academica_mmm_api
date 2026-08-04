<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAsistenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('asistencia')) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['sometimes', 'string', Rule::in(['asistio', 'falto', 'justificado'])],
            'observacion' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $this->input('estado') === 'justificado')],
        ];
    }
}
