<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Calificacion;
use App\Models\Certificado;
use App\Support\MaterialStorage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use RuntimeException;

/** Renders academic certificates to private PDF storage. */
class CertificadoPdfGenerator
{
    public function store(Certificado $certificado): string
    {
        $certificado->loadMissing([
            'miembro.iglesia',
            'tipoCertificado',
            'programacionAcademica.curso.iglesia',
        ]);

        $html = view('certificados.documento', $this->viewData($certificado))->render();
        $pdf = $this->renderPdf($html);

        return MaterialStorage::storeContents($pdf, MaterialStorage::CERTIFICADO_DIRECTORY);
    }

    /** @return array<string, mixed> */
    public function viewData(Certificado $certificado): array
    {
        $miembro = $certificado->miembro;
        $programacion = $certificado->programacionAcademica;
        $curso = $programacion?->curso;
        $iglesia = $curso?->iglesia ?? $miembro?->iglesia;

        $calificacion = null;
        if ($programacion !== null && $miembro !== null) {
            $calificacion = Calificacion::query()
                ->whereHas(
                    'matricula',
                    fn ($query) => $query
                        ->where('programacion_academica_id', $programacion->id)
                        ->where('miembro_id', $miembro->id),
                )
                ->first();
        }

        $documento = null;
        if ($miembro?->tipo_documento && $miembro->numero_documento) {
            $documento = trim($miembro->tipo_documento.' '.$miembro->numero_documento);
        }

        $notaFinal = $calificacion?->nota_final;
        $resultado = $calificacion?->estado === 'aprobada' ? 'APROBADO' : null;

        return [
            'institucion' => $iglesia?->nombre ?: 'Institución',
            'tipo_nombre' => $certificado->tipoCertificado?->nombre ?: 'Certificado',
            'nombre_completo' => $certificado->destinatario ?: ($miembro?->nombre_completo ?: 'Estudiante'),
            'documento' => $documento,
            'curso' => $curso?->nombre,
            'periodo' => $programacion?->periodo,
            'grupo' => $programacion?->grupo,
            'fecha_inicio' => $programacion?->fecha_inicio?->format('d/m/Y'),
            'fecha_fin' => $programacion?->fecha_fin?->format('d/m/Y'),
            'nota_final' => $notaFinal !== null ? number_format((float) $notaFinal, 2, '.', '') : null,
            'resultado' => $resultado,
            'fecha_emision' => $certificado->emitido_at?->format('d/m/Y') ?? now()->format('d/m/Y'),
            'codigo_verificacion' => $certificado->codigo_verificacion,
            'codigo_legible' => $certificado->codigo_legible,
            'qr_data_uri' => $this->qrDataUri($this->verifyUrl($certificado->codigo_verificacion)),
        ];
    }

    public function verifyUrl(string $codigo): string
    {
        $template = (string) config('certificados.verify_url');

        return str_replace('{codigo}', rawurlencode($codigo), $template);
    }

    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->setChroot(storage_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $output = $dompdf->output();
        if (! is_string($output) || $output === '' || ! str_starts_with($output, '%PDF')) {
            throw new RuntimeException('No se pudo generar el PDF del certificado.');
        }

        return $output;
    }

    private function qrDataUri(string $url): string
    {
        $qr = new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 8,
        );

        $png = (new PngWriter())->write($qr)->getString();

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
