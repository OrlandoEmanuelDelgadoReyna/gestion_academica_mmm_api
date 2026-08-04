<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateNotificacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('notificacion')) ?? false;
    }

    public function rules(): array
    {
        return [
            'titulo' => ['sometimes', 'string', 'max:150'],
            'contenido' => ['sometimes', 'string'],
            'tipo' => ['sometimes', 'string', 'max:30'],
        ];
    }
}
