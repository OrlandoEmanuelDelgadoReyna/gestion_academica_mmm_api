<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('matricula')) ?? false;
    }

    public function rules(): array
    {
        return [
            'fecha_matricula' => ['sometimes', 'date'],
        ];
    }
}
