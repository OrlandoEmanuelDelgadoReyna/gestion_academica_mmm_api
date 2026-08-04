<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTareaRequest;
use App\Http\Requests\UpdateTareaRequest;
use App\Http\Resources\TareaResource;
use App\Models\Tarea;
use App\Services\TareaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class TareaController extends Controller
{
    public function __construct(private TareaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Tarea::class);

        return TareaResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreTareaRequest $request): TareaResource
    {
        return new TareaResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Tarea $tarea): TareaResource
    {
        $this->authorize('view', $tarea);

        return new TareaResource($tarea->load(['programacionAcademica', 'creadoPor']));
    }

    public function update(UpdateTareaRequest $request, Tarea $tarea): TareaResource
    {
        return new TareaResource($this->service->update($tarea, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Tarea $tarea): JsonResponse
    {
        $this->authorize('delete', $tarea);

        $this->service->delete($tarea, $request->user()->id);

        return response()->json(status: 204);
    }
}
