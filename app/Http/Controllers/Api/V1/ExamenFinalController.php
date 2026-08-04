<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExamenFinalRequest;
use App\Http\Requests\UpdateExamenFinalRequest;
use App\Http\Resources\ExamenFinalResource;
use App\Models\ExamenFinal;
use App\Services\ExamenFinalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ExamenFinalController extends Controller
{
    public function __construct(private ExamenFinalService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ExamenFinal::class);

        return ExamenFinalResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreExamenFinalRequest $request): ExamenFinalResource
    {
        return new ExamenFinalResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(ExamenFinal $examenFinal): ExamenFinalResource
    {
        $this->authorize('view', $examenFinal);

        return new ExamenFinalResource($examenFinal->load('programacionAcademica'));
    }

    public function update(UpdateExamenFinalRequest $request, ExamenFinal $examenFinal): ExamenFinalResource
    {
        return new ExamenFinalResource($this->service->update($examenFinal, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, ExamenFinal $examenFinal): JsonResponse
    {
        $this->authorize('delete', $examenFinal);

        $this->service->delete($examenFinal, $request->user()->id);

        return response()->json(status: 204);
    }
}
