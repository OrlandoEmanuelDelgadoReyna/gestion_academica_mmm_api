<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRolRequest;
use App\Http\Requests\UpdateRolRequest;
use App\Http\Resources\RolResource;
use App\Models\Rol;
use App\Repositories\Contracts\RolRepositoryInterface;
use App\Services\RolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RolController extends Controller
{
    public function __construct(private RolService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Rol::class);

        return RolResource::collection(app(RolRepositoryInterface::class)->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreRolRequest $request): RolResource
    {
        $this->authorize('create', Rol::class);

        return new RolResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function update(UpdateRolRequest $request, Rol $rol): RolResource
    {
        $this->authorize('update', $rol);

        return new RolResource($this->service->update($rol, $request->validated(), $request->user()->id));
    }

    public function show(Rol $rol): RolResource
    {
        $this->authorize('view', $rol);

        return new RolResource($rol->load('permisos'));
    }

    public function permissions(Rol $rol): RolResource
    {
        $this->authorize('view', $rol);

        return new RolResource($rol->load('permisos'));
    }

    public function syncPermissions(Request $request, Rol $rol): RolResource
    {
        $this->authorize('update', $rol);

        $data = $request->validate(['permisos' => ['array'], 'permisos.*' => ['integer', 'exists:permisos,id']]);

        return new RolResource($this->service->syncPermissions($rol, $data['permisos'] ?? [], $request->user()->id));
    }

    public function revokePermissions(Rol $rol, Request $request): JsonResponse
    {
        $this->authorize('update', $rol);

        $data = $request->validate(['permisos' => ['required', 'array'], 'permisos.*' => ['integer']]);
        $remaining = $rol->permisos()->whereNotIn('permisos.id', $data['permisos'])->pluck('permisos.id')->all();
        $this->service->syncPermissions($rol, $remaining, $request->user()->id);

        return response()->json(status: 204);
    }
}
