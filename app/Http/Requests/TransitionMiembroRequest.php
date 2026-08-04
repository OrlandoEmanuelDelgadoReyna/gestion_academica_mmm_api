<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionMiembroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('miembro')) ?? false;
    }

    public function rules(): array
    {
        return ['estado_membresia_id' => ['required', 'integer', 'exists:estados_membresia,id'], 'fecha_inicio' => ['required', 'date'], 'observacion' => ['nullable', 'string']];
    }
}
