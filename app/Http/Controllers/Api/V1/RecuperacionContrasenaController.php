<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SolicitarRecuperacionContrasenaRequest;
use App\Http\Requests\VerificarRecuperacionContrasenaRequest;
use App\Services\PasswordRecoveryService;
use Illuminate\Http\JsonResponse;

final class RecuperacionContrasenaController extends Controller
{
    public function __construct(private PasswordRecoveryService $service) {}

    public function solicitar(SolicitarRecuperacionContrasenaRequest $request): JsonResponse
    {
        $this->service->request((string) $request->string('correo_electronico'));

        return response()->json(['message' => PasswordRecoveryService::REQUEST_MESSAGE]);
    }

    public function verificar(VerificarRecuperacionContrasenaRequest $request): JsonResponse
    {
        $this->service->reset(
            (string) $request->string('correo_electronico'),
            (string) $request->string('codigo'),
            (string) $request->string('contrasena'),
        );

        return response()->json(['message' => PasswordRecoveryService::SUCCESS_MESSAGE]);
    }
}
