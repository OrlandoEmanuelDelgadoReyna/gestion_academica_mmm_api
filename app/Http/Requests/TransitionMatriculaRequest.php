<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('matricula')) ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', 'string', Rule::in(['activa', 'retirada', 'completada'])],
        ];
    }
}
