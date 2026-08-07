<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesPersonData;
use App\Models\Iglesia;
use App\Support\Validation\PersonDataRules;
use Illuminate\Foundation\Http\FormRequest;

final class StoreIglesiaRequest extends FormRequest
{
    use NormalizesPersonData;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Iglesia::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30', 'unique:iglesias,codigo'],
            'nombre' => ['required', 'string', 'max:150'],
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
