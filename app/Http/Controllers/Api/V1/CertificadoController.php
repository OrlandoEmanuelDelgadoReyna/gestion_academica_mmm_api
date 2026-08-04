<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmitirCertificadoRequest;
use App\Http\Requests\ReemplazarCertificadoRequest;
use App\Http\Requests\RevocarCertificadoRequest;
use App\Http\Resources\CertificadoResource;
use App\Models\Certificado;
use App\Services\CertificadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CertificadoController extends Controller
{
    public function __construct(private CertificadoService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Certificado::class);

        return CertificadoResource::collection($this->service->paginate((int) $request->integer('per_page', 15)));
    }

    public function show(Certificado $certificado): CertificadoResource
    {
        $this->authorize('view', $certificado);

        return new CertificadoResource($certificado->load(['miembro', 'tipoCertificado', 'programacionAcademica']));
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

    public function verificar(string $codigo): JsonResponse|CertificadoResource
    {
        $certificado = $this->service->verificar($codigo);

        if ($certificado === null) {
            return response()->json(['message' => 'Certificado no encontrado o no válido.', 'valido' => false], 404);
        }

        return (new CertificadoResource($certificado))->additional(['valido' => true]);
    }
}
