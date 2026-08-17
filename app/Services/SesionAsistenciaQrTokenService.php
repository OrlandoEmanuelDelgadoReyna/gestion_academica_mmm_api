<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AsistenciaQrException;
use App\Models\Sesion;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/** Issues and verifies encrypted session-attendance QR tokens (APP_KEY, no JWT). */
final class SesionAsistenciaQrTokenService
{
    public const PURPOSE = 'sesion_asistencia';

    public const PAYLOAD_PREFIX = 'MMM-SESION-QR.';

    public function issue(Sesion $sesion): string
    {
        $payload = json_encode([
            'v' => 1,
            't' => self::PURPOSE,
            'sid' => $sesion->id,
        ], JSON_THROW_ON_ERROR);

        return Crypt::encryptString($payload);
    }

    public function payload(string $token): string
    {
        return self::PAYLOAD_PREFIX.$token;
    }

    public function parse(string $raw): int
    {
        $token = trim($raw);
        if (str_starts_with($token, self::PAYLOAD_PREFIX)) {
            $token = substr($token, strlen(self::PAYLOAD_PREFIX));
        }

        if ($token === '') {
            throw AsistenciaQrException::invalid();
        }

        try {
            $decoded = Crypt::decryptString($token);
            /** @var array<string, mixed> $data */
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw AsistenciaQrException::invalid();
        }

        if (($data['t'] ?? null) !== self::PURPOSE || ! isset($data['sid'])) {
            throw AsistenciaQrException::invalid();
        }

        $sesionId = (int) $data['sid'];
        if ($sesionId < 1) {
            throw AsistenciaQrException::invalid();
        }

        return $sesionId;
    }
}
