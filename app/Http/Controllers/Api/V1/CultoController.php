<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCultoRequest;
use App\Http\Requests\UpdateCultoRequest;
use App\Http\Resources\CultoResource;
use App\Models\Culto;
use App\Services\CultoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CultoController extends Controller
{
    public function __construct(private CultoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Culto::class);

        $iglesiaId = $request->filled('iglesia_id') ? $request->integer('iglesia_id') : null;

        return CultoResource::collection($this->service->paginate((int) $request->integer('per_page', 15), $iglesiaId));
    }

    public function store(StoreCultoRequest $request): CultoResource
    {
        return new CultoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Culto $culto): CultoResource
    {
        $this->authorize('view', $culto);

        return new CultoResource($culto->load(['iglesia', 'tipoCulto', 'bloques']));
    }

    public function update(UpdateCultoRequest $request, Culto $culto): CultoResource
    {
        return new CultoResource($this->service->update($culto, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Culto $culto): JsonResponse
    {
        $this->authorize('delete', $culto);

        $this->service->delete($culto, $request->user()->id);

        return response()->json(status: 204);
    }
}
