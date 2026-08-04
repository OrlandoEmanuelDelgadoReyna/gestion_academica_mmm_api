<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Anuncio;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAnuncioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Anuncio::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'contenido' => ['required', 'string'],
            'estado' => ['required', 'string', 'max:30'],
            'publicado_at' => ['nullable', 'date'],
            'vence_at' => ['nullable', 'date', 'after_or_equal:publicado_at'],
        ];
    }
}
