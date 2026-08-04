<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Matricula;
use Illuminate\Foundation\Http\FormRequest;

final class StoreMatriculaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Matricula::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'programacion_academica_id' => ['required', 'integer', 'exists:programaciones_academicas,id'],
            'miembro_id' => ['required', 'integer', 'exists:miembros,id'],
        ];
    }
}
