<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Culto;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCultoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Culto::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'tipo_culto_id' => ['required', 'integer', 'exists:tipos_culto,id'],
            'inicio_at' => ['required', 'date'],
            'fin_at' => ['required', 'date', 'after:inicio_at'],
            'lugar' => ['nullable', 'string', 'max:150'],
            'estado' => ['required', 'string', 'max:30'],
        ];
    }
}
