<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSesionRequest;
use App\Http\Requests\UpdateSesionRequest;
use App\Http\Resources\SesionResource;
use App\Models\ProgramacionAcademica;
use App\Models\Sesion;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\SesionAsistenciaQrTokenService;
use App\Services\SesionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SesionController extends Controller
{
    public function __construct(
        private SesionService $service,
        private SesionAsistenciaQrTokenService $qrTokens,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sesion::class);

        $programacionId = $request->filled('programacion_academica_id')
            ? $request->integer('programacion_academica_id')
            : null;

        /** @var Usuario $user */
        $user = $request->user();

        if ($programacionId !== null && $programacionId > 0) {
            $programacion = ProgramacionAcademica::query()->find($programacionId);
            if ($programacion !== null) {
                $this->authorize('view', $programacion);
            }
        }

        return SesionResource::collection($this->service->paginate(
            (int) $request->integer('per_page', 15),
            ($programacionId !== null && $programacionId > 0) ? $programacionId : null,
            $this->academicAccess->listScopeMiembroId($user),
        ));
    }

    public function store(StoreSesionRequest $request): SesionResource
    {
        return new SesionResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Sesion $sesion): SesionResource
    {
        $this->authorize('view', $sesion);

        return new SesionResource($sesion->load(['programacionAcademica.curso', 'lecciones']));
    }

    public function update(UpdateSesionRequest $request, Sesion $sesion): SesionResource
    {
        return new SesionResource($this->service->update($sesion, $request->validated(), $request->user()->id));
    }

    public function qr(Sesion $sesion): JsonResponse
    {
        $this->authorize('view', $sesion);

        $sesion->load(['programacionAcademica.curso']);
        $token = $this->qrTokens->issue($sesion);

        return response()->json([
            'data' => [
                'token' => $token,
                'payload' => $this->qrTokens->payload($token),
                'sesion' => (new SesionResource($sesion))->resolve(),
            ],
        ]);
    }
}
