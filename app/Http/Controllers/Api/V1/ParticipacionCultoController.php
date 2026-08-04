<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParticipacionCultoRequest;
use App\Http\Requests\UpdateParticipacionCultoRequest;
use App\Http\Resources\ParticipacionCultoResource;
use App\Models\ParticipacionCulto;
use App\Services\ParticipacionCultoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ParticipacionCultoController extends Controller
{
    public function __construct(private ParticipacionCultoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ParticipacionCulto::class);

        $bloqueCultoId = $request->filled('bloque_culto_id') ? $request->integer('bloque_culto_id') : null;

        return ParticipacionCultoResource::collection($this->service->paginate((int) $request->integer('per_page', 15), $bloqueCultoId));
    }

    public function store(StoreParticipacionCultoRequest $request): ParticipacionCultoResource
    {
        return new ParticipacionCultoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(ParticipacionCulto $participacionCulto): ParticipacionCultoResource
    {
        $this->authorize('view', $participacionCulto);

        return new ParticipacionCultoResource($participacionCulto->load(['bloqueCulto.culto', 'miembro']));
    }

    public function update(UpdateParticipacionCultoRequest $request, ParticipacionCulto $participacionCulto): ParticipacionCultoResource
    {
        return new ParticipacionCultoResource($this->service->update($participacionCulto, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, ParticipacionCulto $participacionCulto): JsonResponse
    {
        $this->authorize('delete', $participacionCulto);

        $this->service->delete($participacionCulto, $request->user()->id);

        return response()->json(status: 204);
    }
}
