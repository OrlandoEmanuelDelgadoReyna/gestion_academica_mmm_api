<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Anuncio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAnuncioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('anuncio')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'contenido' => ['sometimes', 'string'],
            'estado' => ['sometimes', 'string', Rule::in(Anuncio::ESTADOS)],
            'publicado_at' => ['nullable', 'date'],
            'vence_at' => ['nullable', 'date'],
        ];
    }
}
