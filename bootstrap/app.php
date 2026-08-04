<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['usuario.activo' => EnsureUserIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('UNAUTHENTICATED', 'Autenticación requerida.', 401);
            }
        });
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('VALIDATION_ERROR', 'Los datos enviados no son válidos.', 422, $exception->errors());
            }
        });
        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponse::error('HTTP_ERROR', $exception->getMessage() ?: 'La solicitud no puede procesarse.', $exception->getStatusCode());
            }
        });
    })->create();
