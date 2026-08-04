<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMiembroRequest;
use App\Http\Requests\TransitionMiembroRequest;
use App\Http\Requests\UpdateMiembroRequest;
use App\Http\Resources\MiembroResource;
use App\Models\Miembro;
use App\Services\MiembroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MiembroController extends Controller
{
    public function __construct(private MiembroService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Miembro::class);

        return MiembroResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function store(StoreMiembroRequest $request): MiembroResource
    {
        return new MiembroResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Miembro $miembro): MiembroResource
    {
        $this->authorize('view', $miembro);

        return new MiembroResource($miembro);
    }

    public function update(UpdateMiembroRequest $request, Miembro $miembro): MiembroResource
    {
        return new MiembroResource($this->service->update($miembro, $request->validated(), $request->user()->id));
    }

    public function transition(TransitionMiembroRequest $request, Miembro $miembro): JsonResponse
    {
        $this->service->transition($miembro, (int) $request->integer('estado_membresia_id'), (string) $request->string('fecha_inicio'), $request->string('observacion')->toString() ?: null, $request->user()->id);

        return response()->json(status: 204);
    }

    public function destroy(Request $request, Miembro $miembro): JsonResponse
    {
        $this->authorize('delete', $miembro);

        $miembro->delete();

        return response()->json(status: 204);
    }
}
