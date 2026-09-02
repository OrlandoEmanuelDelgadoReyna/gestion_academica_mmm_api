<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalificarEntregaTareaRequest;
use App\Http\Requests\StoreEntregaTareaRequest;
use App\Http\Requests\UpdateEntregaTareaRequest;
use App\Http\Resources\EntregaTareaResource;
use App\Models\EntregaTarea;
use App\Models\Tarea;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\EntregaTareaService;
use App\Support\MaterialStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EntregaTareaController extends Controller
{
    public function __construct(
        private EntregaTareaService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', EntregaTarea::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'tarea_id' => ['sometimes', 'integer', 'exists:tareas,id'],
        ]);

        $tareaId = isset($validated['tarea_id']) ? (int) $validated['tarea_id'] : null;

        /** @var Usuario $user */
        $user = $request->user();

        if ($tareaId !== null) {
            $tarea = Tarea::query()->findOrFail($tareaId);
            $this->authorize('view', $tarea);
        }

        return EntregaTareaResource::collection($this->service->paginate(
            (int) ($validated['per_page'] ?? 15),
            $tareaId,
            $this->academicAccess->teacherListMiembroId($user),
            $this->academicAccess->studentListMiembroId($user),
        ));
    }

    public function store(StoreEntregaTareaRequest $request): EntregaTareaResource
    {
        $data = $request->safe()->except('archivo');

        return new EntregaTareaResource(
            $this->service->create($data, $request->user()->id, $request->file('archivo')),
        );
    }

    public function show(EntregaTarea $entregaTarea): EntregaTareaResource
    {
        $this->authorize('view', $entregaTarea);

        return new EntregaTareaResource($entregaTarea->load(['tarea', 'matricula.miembro', 'calificadoPor.miembro']));
    }

    public function calificar(CalificarEntregaTareaRequest $request, EntregaTarea $entregaTarea): EntregaTareaResource
    {
        return new EntregaTareaResource(
            $this->service->grade($entregaTarea, $request->safe()->only(['nota', 'retroalimentacion']), $request->user()->id),
        );
    }

    public function update(UpdateEntregaTareaRequest $request, EntregaTarea $entregaTarea): EntregaTareaResource
    {
        $data = $request->safe()->except('archivo');

        return new EntregaTareaResource(
            $this->service->update($entregaTarea, $data, $request->user()->id, $request->file('archivo')),
        );
    }

    public function descargar(EntregaTarea $entregaTarea): StreamedResponse
    {
        $this->authorize('view', $entregaTarea);

        if (! MaterialStorage::exists($entregaTarea->ruta_archivo)) {
            throw new NotFoundHttpException('El archivo de la entrega no está disponible.');
        }

        $original = $entregaTarea->nombre_original ?: 'entrega';
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $extension = pathinfo($original, PATHINFO_EXTENSION);
        if ($extension === '') {
            $extension = pathinfo((string) $entregaTarea->ruta_archivo, PATHINFO_EXTENSION);
        }
        $safe = Str::slug($basename) ?: 'entrega';
        $downloadName = $extension !== '' ? $safe.'.'.$extension : $safe;

        return Storage::disk(MaterialStorage::DISK)->download(
            $entregaTarea->ruta_archivo,
            $downloadName,
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }
}
