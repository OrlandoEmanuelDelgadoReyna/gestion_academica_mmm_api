<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCriterioEvaluacionRequest;
use App\Http\Requests\UpdateCriterioEvaluacionRequest;
use App\Http\Resources\CriterioEvaluacionResource;
use App\Models\CriterioEvaluacion;
use App\Services\CriterioEvaluacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CriterioEvaluacionController extends Controller
{
    public function __construct(private CriterioEvaluacionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CriterioEvaluacion::class);

        return CriterioEvaluacionResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreCriterioEvaluacionRequest $request): CriterioEvaluacionResource
    {
        return new CriterioEvaluacionResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(CriterioEvaluacion $criterioEvaluacion): CriterioEvaluacionResource
    {
        $this->authorize('view', $criterioEvaluacion);

        return new CriterioEvaluacionResource($criterioEvaluacion->load('programacionAcademica'));
    }

    public function update(UpdateCriterioEvaluacionRequest $request, CriterioEvaluacion $criterioEvaluacion): CriterioEvaluacionResource
    {
        return new CriterioEvaluacionResource($this->service->update($criterioEvaluacion, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, CriterioEvaluacion $criterioEvaluacion): JsonResponse
    {
        $this->authorize('delete', $criterioEvaluacion);

        $this->service->delete($criterioEvaluacion, $request->user()->id);

        return response()->json(status: 204);
    }
}
