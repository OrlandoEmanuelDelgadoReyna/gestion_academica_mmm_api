<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['contrasena_actual' => ['required', 'string'], 'contrasena' => ['required', 'string', 'min:12', 'confirmed']];
    }
}
