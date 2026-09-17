<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAnuncioRequest;
use App\Http\Requests\UpdateAnuncioRequest;
use App\Http\Resources\AnuncioResource;
use App\Models\Anuncio;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\AnuncioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AnuncioController extends Controller
{
    public function __construct(
        private AnuncioService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Anuncio::class);

        /** @var Usuario $user */
        $user = $request->user();
        $iglesiaId = $this->requireIglesiaId($user);

        if ($request->filled('iglesia_id') && $request->integer('iglesia_id') !== $iglesiaId) {
            abort(403, 'No puede consultar anuncios de otra iglesia.');
        }

        return AnuncioResource::collection(
            $this->service->paginate((int) $request->integer('per_page', 15), $iglesiaId),
        );
    }

    public function publicados(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewPublicados', Anuncio::class);

        /** @var Usuario $user */
        $user = $request->user();
        $iglesiaId = $this->requireIglesiaId($user);

        return AnuncioResource::collection(
            $this->service->paginatePublicados($user, (int) $request->integer('per_page', 15), $iglesiaId),
        );
    }

    public function store(StoreAnuncioRequest $request): AnuncioResource
    {
        return new AnuncioResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Anuncio $anuncio): AnuncioResource
    {
        $this->authorize('view', $anuncio);

        return new AnuncioResource($anuncio->load(['iglesia', 'creadoPor']));
    }

    public function update(UpdateAnuncioRequest $request, Anuncio $anuncio): AnuncioResource
    {
        return new AnuncioResource($this->service->update($anuncio, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Anuncio $anuncio): JsonResponse
    {
        $this->authorize('delete', $anuncio);

        $this->service->delete($anuncio, $request->user()->id);

        return response()->json(status: 204);
    }

    private function requireIglesiaId(Usuario $user): int
    {
        $iglesiaId = $this->academicAccess->iglesiaId($user);
        if ($iglesiaId === null) {
            abort(403, 'No tiene una iglesia asignada.');
        }

        return $iglesiaId;
    }
}
