<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rejects requests made by deactivated accounts after authentication. */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null && ! $request->user()->activo) {
            abort(403, 'La cuenta de usuario se encuentra inactiva.');
        }

        return $next($request);
    }
}
