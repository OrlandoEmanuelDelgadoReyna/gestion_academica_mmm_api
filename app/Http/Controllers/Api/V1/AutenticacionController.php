<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UsuarioResource;
use App\Services\AutenticacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AutenticacionController extends Controller
{
    public function __construct(private AutenticacionService $service) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->service->login((string) $request->string('nombre_usuario'), (string) $request->string('contrasena'), $request->string('nombre_dispositivo')->toString());

        return response()->json(['token' => $result['token'], 'token_type' => 'Bearer', 'usuario' => new UsuarioResource($result['usuario'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->service->logout($request->user());

        return response()->json(status: 204);
    }

    public function me(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->loadMissing(['miembro', 'roles']));
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->service->changePassword($request->user(), (string) $request->string('contrasena_actual'), (string) $request->string('contrasena'));

        return response()->json(status: 204);
    }
}
