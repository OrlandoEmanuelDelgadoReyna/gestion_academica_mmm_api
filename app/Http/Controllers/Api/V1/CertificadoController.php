<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarElegibilidadCertificadoRequest;
use App\Http\Requests\EmitirCertificadoRequest;
use App\Http\Requests\ReemplazarCertificadoRequest;
use App\Http\Requests\RevocarCertificadoRequest;
use App\Http\Resources\CertificadoResource;
use App\Http\Resources\CertificadoVerificacionPublicaResource;
use App\Models\Certificado;
use App\Models\Usuario;
use App\Services\AcademicAccess;
use App\Services\CertificadoService;
use App\Support\MaterialStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CertificadoController extends Controller
{
    public function __construct(
        private CertificadoService $service,
        private AcademicAccess $academicAccess,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Certificado::class);

        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'programacion_academica_id' => ['sometimes', 'integer', 'exists:programaciones_academicas,id'],
            'miembro_id' => ['sometimes', 'integer', 'exists:miembros,id'],
        ]);

        /** @var Usuario $user */
        $user = $request->user();

        $programacionId = isset($validated['programacion_academica_id'])
            ? (int) $validated['programacion_academica_id']
            : null;
        $miembroId = isset($validated['miembro_id']) ? (int) $validated['miembro_id'] : null;

        if ($programacionId !== null && ! $this->academicAccess->canViewProgramacionId($user, $programacionId)
            && ! $this->academicAccess->hasAnyEnrollment($user, $programacionId)
            && ! $this->academicAccess->isGlobalAcademic($user)
        ) {
            abort(403);
        }

        if ($miembroId !== null && ! $this->academicAccess->isGlobalAcademic($user)
            && ! ($this->academicAccess->isDocente($user) && $programacionId !== null && $this->academicAccess->isAssignedToProgramacionId($user, $programacionId))
            && (int) $user->miembro_id !== $miembroId
        ) {
            abort(403);
        }

        return CertificadoResource::collection($this->service->paginate(
            $user,
            (int) ($validated['per_page'] ?? 15),
            $programacionId,
            $miembroId,
        ));
    }

    public function show(Certificado $certificado): CertificadoResource
    {
        $this->authorize('view', $certificado);

        return new CertificadoResource($certificado->load([
            'miembro',
            'tipoCertificado',
            'programacionAcademica.curso',
        ]));
    }

    public function elegibilidad(ConsultarElegibilidadCertificadoRequest $request): JsonResponse
    {
        $data = $this->service->evaluarElegibilidad(
            (int) $request->validated('miembro_id'),
            (int) $request->validated('programacion_academica_id'),
            $request->user()->id,
            $request->filled('tipo_certificado_id') ? (int) $request->validated('tipo_certificado_id') : null,
        );

        return response()->json(['data' => $data]);
    }

    public function emitir(EmitirCertificadoRequest $request): CertificadoResource
    {
        return new CertificadoResource($this->service->emitir($request->validated(), $request->user()->id));
    }

    public function revocar(RevocarCertificadoRequest $request, Certificado $certificado): CertificadoResource
    {
        return new CertificadoResource($this->service->revocar($certificado, $request->validated('motivo'), $request->user()->id));
    }

    public function reemplazar(ReemplazarCertificadoRequest $request, Certificado $certificado): CertificadoResource
    {
        return new CertificadoResource($this->service->reemplazar($certificado, $request->validated(), $request->user()->id));
    }

    public function descargar(Certificado $certificado): StreamedResponse
    {
        $this->authorize('descargar', $certificado);

        if (! MaterialStorage::exists($certificado->ruta_documento)) {
            throw new NotFoundHttpException('El documento del certificado no está disponible.');
        }

        $downloadName = Str::slug($certificado->codigo_legible ?: 'certificado').'.pdf';

        return Storage::disk(MaterialStorage::DISK)->download(
            $certificado->ruta_documento,
            $downloadName,
            [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function verificar(string $codigo): JsonResponse
    {
        $certificado = $this->service->verificar($codigo);

        if ($certificado === null) {
            return response()->json([
                'message' => 'Certificado no encontrado o no válido.',
                'valido' => false,
            ], 404);
        }

        $valido = $certificado->esValidoPublicamente();

        return (new CertificadoVerificacionPublicaResource($certificado))
            ->additional([
                'valido' => $valido,
                'message' => $valido ? null : 'Certificado no válido.',
            ])
            ->response();
    }
}
