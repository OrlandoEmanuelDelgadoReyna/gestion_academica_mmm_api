<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPersonData;
use App\Support\Validation\PersonDataRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateIglesiaRequest extends FormRequest
{
    use NormalizesPersonData;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('iglesia')) ?? false;
    }

    public function rules(): array
    {
        return [
            'codigo' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('iglesias', 'codigo')->ignore($this->route('iglesia')),
            ],
            'nombre' => ['sometimes', 'string', 'max:150'],
            'direccion' => PersonDataRules::direccion(),
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo_electronico' => PersonDataRules::correo(),
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return PersonDataRules::messages();
    }
}
