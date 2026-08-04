<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('culto')) ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo_culto_id' => ['sometimes', 'integer', 'exists:tipos_culto,id'],
            'inicio_at' => ['sometimes', 'date'],
            'fin_at' => ['sometimes', 'date', 'after:inicio_at'],
            'lugar' => ['nullable', 'string', 'max:150'],
            'estado' => ['sometimes', 'string', 'max:30'],
        ];
    }
}
