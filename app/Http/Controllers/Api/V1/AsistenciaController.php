<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAsistenciaQrRequest;
use App\Http\Requests\StoreAsistenciaRequest;
use App\Http\Requests\UpdateAsistenciaRequest;
use App\Http\Resources\AsistenciaResource;
use App\Exceptions\AsistenciaQrException;
use App\Models\Asistencia;
use App\Models\Usuario;
use App\Services\AsistenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AsistenciaController extends Controller
{
    public function __construct(private AsistenciaService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asistencia::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sesion_id' => ['sometimes', 'integer', 'exists:sesiones,id'],
        ]);

        $sesionId = isset($validated['sesion_id']) ? (int) $validated['sesion_id'] : null;

        return AsistenciaResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $sesionId,
        ));
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

    public function storeFromQr(RegisterAsistenciaQrRequest $request): JsonResponse
    {
        /** @var Usuario $user */
        $user = $request->user();

        $asistencia = $this->service->registerFromQr(
            (string) $request->string('token'),
            $user,
        )->load(['sesion.programacionAcademica.curso', 'matricula.miembro']);

        return response()->json([
            'message' => 'Asistencia registrada correctamente.',
            'code' => AsistenciaQrException::ASISTENCIA_REGISTRADA,
            'data' => (new AsistenciaResource($asistencia))->resolve($request),
        ], 201);
    }
}
