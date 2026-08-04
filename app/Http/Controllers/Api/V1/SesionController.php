<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSesionRequest;
use App\Http\Requests\UpdateSesionRequest;
use App\Http\Resources\SesionResource;
use App\Models\Sesion;
use App\Services\SesionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SesionController extends Controller
{
    public function __construct(private SesionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sesion::class);

        return SesionResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreSesionRequest $request): SesionResource
    {
        return new SesionResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Sesion $sesion): SesionResource
    {
        $this->authorize('view', $sesion);

        return new SesionResource($sesion->load(['programacionAcademica.curso', 'lecciones']));
    }

    public function update(UpdateSesionRequest $request, Sesion $sesion): SesionResource
    {
        return new SesionResource($this->service->update($sesion, $request->validated(), $request->user()->id));
    }
}
