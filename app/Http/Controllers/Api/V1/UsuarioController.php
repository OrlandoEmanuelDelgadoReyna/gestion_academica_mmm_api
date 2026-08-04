<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\CrearUsuarioData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** HTTP adapter for user-management use cases. */
final class UsuarioController extends Controller
{
    public function __construct(private UsuarioService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Usuario::class);

        return UsuarioResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreUsuarioRequest $request): JsonResponse
    {
        $user = $this->service->create(new CrearUsuarioData((int) $request->integer('miembro_id'), (string) $request->string('nombre_usuario'), (string) $request->string('contrasena'), $request->collect('roles')->map(fn ($id) => (int) $id)->all()), $request->user()?->id);

        return (new UsuarioResource($user))->response()->setStatusCode(201);
    }

    public function show(Usuario $usuario): UsuarioResource
    {
        $this->authorize('view', $usuario);

        return new UsuarioResource($this->service->find($usuario->id));
    }

    public function destroy(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('delete', $usuario);
        $this->service->deactivate($usuario, $request->user()?->id);

        return response()->json(status: 204);
    }
}
