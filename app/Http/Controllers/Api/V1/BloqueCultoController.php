<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBloqueCultoRequest;
use App\Http\Requests\UpdateBloqueCultoRequest;
use App\Http\Resources\BloqueCultoResource;
use App\Models\BloqueCulto;
use App\Services\BloqueCultoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class BloqueCultoController extends Controller
{
    public function __construct(private BloqueCultoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BloqueCulto::class);

        $cultoId = $request->filled('culto_id') ? $request->integer('culto_id') : null;

        return BloqueCultoResource::collection($this->service->paginate((int) $request->integer('per_page', 15), $cultoId));
    }

    public function store(StoreBloqueCultoRequest $request): BloqueCultoResource
    {
        return new BloqueCultoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(BloqueCulto $bloqueCulto): BloqueCultoResource
    {
        $this->authorize('view', $bloqueCulto);

        return new BloqueCultoResource($bloqueCulto->load(['culto', 'tipoParticipacion', 'participaciones.miembro']));
    }

    public function update(UpdateBloqueCultoRequest $request, BloqueCulto $bloqueCulto): BloqueCultoResource
    {
        return new BloqueCultoResource($this->service->update($bloqueCulto, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, BloqueCulto $bloqueCulto): JsonResponse
    {
        $this->authorize('delete', $bloqueCulto);

        $this->service->delete($bloqueCulto, $request->user()->id);

        return response()->json(status: 204);
    }
}
