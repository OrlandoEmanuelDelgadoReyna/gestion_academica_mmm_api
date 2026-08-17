<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Sesion;
use Illuminate\Foundation\Http\FormRequest;

final class GenerateSesionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Sesion::class) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
