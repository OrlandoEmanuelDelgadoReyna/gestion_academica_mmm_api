<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Certificado {{ $codigo_legible }}</title>
    <style>
        @page { margin: 36px 42px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1b2430;
            font-size: 12px;
            margin: 0;
        }
        .frame {
            border: 3px solid #1f4e5f;
            padding: 28px 32px;
        }
        .inner {
            border: 1px solid #c9a227;
            padding: 24px 28px;
        }
        .brand {
            text-align: center;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #1f4e5f;
            font-size: 13px;
            font-weight: bold;
        }
        .title {
            text-align: center;
            font-size: 28px;
            letter-spacing: 6px;
            color: #c9a227;
            margin: 18px 0 8px;
            font-weight: bold;
        }
        .subtitle {
            text-align: center;
            color: #4a5560;
            margin-bottom: 22px;
        }
        .recipient {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin: 10px 0 4px;
        }
        .meta {
            text-align: center;
            color: #4a5560;
            margin-bottom: 18px;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0 20px;
        }
        table.details td {
            padding: 6px 4px;
            vertical-align: top;
        }
        .label {
            width: 38%;
            color: #5b6773;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .value {
            font-weight: bold;
        }
        .footer {
            margin-top: 16px;
        }
        .footer td {
            vertical-align: middle;
        }
        .codes {
            font-size: 11px;
            color: #4a5560;
        }
        .codes strong {
            color: #1b2430;
        }
        .qr {
            text-align: right;
        }
        .qr img {
            width: 96px;
            height: 96px;
        }
        .hint {
            font-size: 9px;
            color: #7a8690;
            margin-top: 4px;
        }
    </style>
</head>
<body>
<div class="frame">
    <div class="inner">
        <div class="brand">{{ $institucion }}</div>
        <div class="title">CERTIFICADO</div>
        <div class="subtitle">{{ $tipo_nombre }}</div>

        <div style="text-align:center;color:#4a5560;">Se certifica que</div>
        <div class="recipient">{{ $nombre_completo }}</div>
        @if($documento)
            <div class="meta">{{ $documento }}</div>
        @endif

        <table class="details">
            @if($curso)
                <tr>
                    <td class="label">Curso</td>
                    <td class="value">{{ $curso }}</td>
                </tr>
            @endif
            @if($periodo)
                <tr>
                    <td class="label">Periodo académico</td>
                    <td class="value">{{ $periodo }}</td>
                </tr>
            @endif
            @if($grupo)
                <tr>
                    <td class="label">Grupo</td>
                    <td class="value">{{ $grupo }}</td>
                </tr>
            @endif
            @if($fecha_inicio)
                <tr>
                    <td class="label">Fecha de inicio</td>
                    <td class="value">{{ $fecha_inicio }}</td>
                </tr>
            @endif
            @if($fecha_fin)
                <tr>
                    <td class="label">Fecha de finalización</td>
                    <td class="value">{{ $fecha_fin }}</td>
                </tr>
            @endif
            @if($nota_final !== null)
                <tr>
                    <td class="label">Nota final</td>
                    <td class="value">{{ $nota_final }}</td>
                </tr>
            @endif
            @if($resultado)
                <tr>
                    <td class="label">Resultado</td>
                    <td class="value">{{ $resultado }}</td>
                </tr>
            @endif
            <tr>
                <td class="label">Fecha de emisión</td>
                <td class="value">{{ $fecha_emision }}</td>
            </tr>
        </table>

        <table class="footer">
            <tr>
                <td>
                    <div class="codes">
                        Código de verificación:<br>
                        <strong>{{ $codigo_verificacion }}</strong><br>
                        {{ $codigo_legible }}
                    </div>
                    <div class="hint">Escanee el código QR para validar este documento.</div>
                </td>
                <td class="qr">
                    <img src="{{ $qr_data_uri }}" alt="QR de verificación">
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
