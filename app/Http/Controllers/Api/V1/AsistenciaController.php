<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAsistenciaRequest;
use App\Http\Requests\UpdateAsistenciaRequest;
use App\Http\Resources\AsistenciaResource;
use App\Models\Asistencia;
use App\Services\AsistenciaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asistencia::class);

        return AsistenciaResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreAsistenciaRequest $request): AsistenciaResource
    {
        return new AsistenciaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Asistencia $asistencia): AsistenciaResource
    {
        $this->authorize('view', $asistencia);

        return new AsistenciaResource($asistencia->load(['sesion', 'matricula.miembro']));
    }

    public function update(UpdateAsistenciaRequest $request, Asistencia $asistencia): AsistenciaResource
    {
        return new AsistenciaResource($this->service->update($asistencia, $request->validated(), $request->user()->id));
    }
}
