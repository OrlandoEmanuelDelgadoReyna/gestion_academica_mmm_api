<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePermisoRequest;
use App\Http\Requests\UpdatePermisoRequest;
use App\Http\Resources\PermisoResource;
use App\Models\Permiso;
use App\Services\PermisoService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PermisoController extends Controller
{
    public function __construct(private PermisoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Permiso::class);

        return PermisoResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StorePermisoRequest $request): PermisoResource
    {
        $this->authorize('create', Permiso::class);

        return new PermisoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Permiso $permiso): PermisoResource
    {
        $this->authorize('view', $permiso);

        return new PermisoResource($permiso);
    }

    public function update(UpdatePermisoRequest $request, Permiso $permiso): PermisoResource
    {
        $this->authorize('update', $permiso);

        return new PermisoResource($this->service->update($permiso, $request->validated(), $request->user()->id));
    }
}
