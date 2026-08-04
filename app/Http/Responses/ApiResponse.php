<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/** Builds the stable API envelope used by all versioned endpoints. */
final class ApiResponse
{
    public static function error(string $code, string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json(['message' => $message, 'code' => $code, 'errors' => $errors], $status);
    }
}
