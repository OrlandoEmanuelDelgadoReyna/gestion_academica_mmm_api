<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/** Builds the stable API envelope used by all versioned endpoints. */
final class ApiResponse
{
    /**
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $extra
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        array $errors = [],
        array $extra = [],
    ): JsonResponse {
        return response()->json([
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
            ...$extra,
        ], $status);
    }
}
