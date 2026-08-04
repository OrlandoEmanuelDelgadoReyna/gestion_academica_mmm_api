<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EnviarNotificacionRequest;
use App\Http\Requests\StoreNotificacionRequest;
use App\Http\Requests\UpdateNotificacionRequest;
use App\Http\Resources\NotificacionDestinatarioResource;
use App\Http\Resources\NotificacionResource;
use App\Models\Notificacion;
use App\Services\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class NotificacionController extends Controller
{
    public function __construct(private NotificacionService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Notificacion::class);

        $iglesiaId = $request->filled('iglesia_id') ? $request->integer('iglesia_id') : null;

        return NotificacionResource::collection($this->service->paginate((int) $request->integer('per_page', 15), $iglesiaId));
    }

    public function store(StoreNotificacionRequest $request): NotificacionResource
    {
        return new NotificacionResource($this->service->create($request->validated(), $request->user()->id));
    }

    public function show(Notificacion $notificacion): NotificacionResource
    {
        $this->authorize('view', $notificacion);

        return new NotificacionResource($notificacion->load(['iglesia', 'destinatarios.usuario']));
    }

    public function update(UpdateNotificacionRequest $request, Notificacion $notificacion): NotificacionResource
    {
        return new NotificacionResource($this->service->update($notificacion, $request->validated(), $request->user()->id));
    }

    public function destroy(Request $request, Notificacion $notificacion): JsonResponse
    {
        $this->authorize('delete', $notificacion);

        $this->service->delete($notificacion, $request->user()->id);

        return response()->json(status: 204);
    }

    public function enviar(EnviarNotificacionRequest $request, Notificacion $notificacion): NotificacionResource
    {
        return new NotificacionResource(
            $this->service->enviar($notificacion, $request->validated('usuario_ids'), $request->user()->id),
        );
    }

    public function marcarLeida(Request $request, Notificacion $notificacion): NotificacionDestinatarioResource
    {
        $this->authorize('marcarLeida', $notificacion);

        return new NotificacionDestinatarioResource(
            $this->service->marcarLeida($notificacion, $request->user()->id),
        );
    }
}
