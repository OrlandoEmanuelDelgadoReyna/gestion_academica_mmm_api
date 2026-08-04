<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Evento;
use Illuminate\Foundation\Http\FormRequest;

final class StoreEventoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Evento::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'iglesia_id' => ['required', 'integer', 'exists:iglesias,id'],
            'titulo' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'inicio_at' => ['required', 'date'],
            'fin_at' => ['required', 'date', 'after:inicio_at'],
            'lugar' => ['nullable', 'string', 'max:150'],
            'estado' => ['required', 'string', 'max:30'],
        ];
    }
}
