<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\Validation\PersonDataNormalizer;

trait NormalizesPersonData
{
    protected function prepareForValidation(): void
    {
        $this->merge(PersonDataNormalizer::normalizePayload($this->all()));
    }
}
