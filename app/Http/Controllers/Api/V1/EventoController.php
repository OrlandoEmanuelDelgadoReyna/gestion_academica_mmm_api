<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventoRequest;
use App\Http\Requests\UpdateEventoRequest;
use App\Http\Resources\EventoResource;
use App\Models\Evento;
use App\Services\EventoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class EventoController extends Controller
{
    public function __construct(private EventoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Evento::class);

        $iglesiaId = $request->filled('iglesia_id') ? $request->integer('iglesia_id') : null;

        return EventoResource::collection($this->service->paginate((int) $request->integer('per_page', 15), $iglesiaId));
    }

    public function store(StoreEventoRequest $request): EventoResource
    {
        return new EventoResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Evento $evento): EventoResource
    {
        $this->authorize('view', $evento);

        return new EventoResource($evento->load('iglesia'));
    }

    public function update(UpdateEventoRequest $request, Evento $evento): EventoResource
    {
        return new EventoResource($this->service->update($evento, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Evento $evento): JsonResponse
    {
        $this->authorize('delete', $evento);

        $this->service->delete($evento, $request->user()->id);

        return response()->json(status: 204);
    }
}
