<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionProgramacionAcademicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('programacionAcademica')) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(['borrador', 'abierta', 'en_curso', 'cerrada', 'cancelada'])],
        ];
    }
}
