<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Miembro;
use App\Models\PasswordResetCode;
use App\Models\Usuario;
use App\Notifications\PasswordResetCodeNotification;
use App\Repositories\Contracts\AuditoriaRepositoryInterface;
use App\Repositories\Contracts\DatabaseTransactionRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Public password recovery. Enumeration-safe: request always looks the same.
 *
 * Development: MAIL_MAILER=log writes the code to storage/logs/laravel.log.
 */
final class PasswordRecoveryService
{
    public const REQUEST_MESSAGE = 'Si el correo está registrado, recibirás un código de recuperación.';

    public const INVALID_CODE_MESSAGE = 'El código no es válido o ha expirado.';

    public const SUCCESS_MESSAGE = 'Contraseña restablecida correctamente. Inicia sesión nuevamente.';

    private const TTL_MINUTES = 10;

    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private AuditoriaRepositoryInterface $auditorias,
        private DatabaseTransactionRepositoryInterface $transactions,
    ) {}

    public function request(string $correoElectronico): void
    {
        $usuario = $this->findActiveUsuarioByEmail($correoElectronico);
        if ($usuario === null) {
            return;
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::query()
            ->where('usuario_id', $usuario->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        PasswordResetCode::query()->create([
            'usuario_id' => $usuario->id,
            'codigo_hash' => Hash::make($codigo),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'attempts' => 0,
            'used_at' => null,
        ]);

        $usuario->loadMissing('miembro');
        $nombre = $usuario->miembro?->nombre_completo ?: $usuario->nombre_usuario;
        $usuario->notify(new PasswordResetCodeNotification($codigo, $nombre));
    }

    public function reset(string $correoElectronico, string $codigo, string $contrasena): void
    {
        $accepted = $this->transactions->execute(function () use ($correoElectronico, $codigo, $contrasena): bool {
            $usuario = $this->findActiveUsuarioByEmail($correoElectronico);
            if ($usuario === null) {
                return false;
            }

            /** @var PasswordResetCode|null $solicitud */
            $solicitud = PasswordResetCode::query()
                ->where('usuario_id', $usuario->id)
                ->whereNull('used_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($solicitud === null
                || $solicitud->used_at !== null
                || $solicitud->expires_at->isPast()
                || $solicitud->attempts >= self::MAX_ATTEMPTS
            ) {
                return false;
            }

            if (! Hash::check($codigo, $solicitud->codigo_hash)) {
                $solicitud->increment('attempts');

                return false;
            }

            $usuario->forceFill(['contrasena' => $contrasena])->save();
            $usuario->tokens()->delete();
            $solicitud->forceFill(['used_at' => now()])->save();

            $this->auditorias->record(
                $usuario->id,
                'PASSWORD_RESET',
                'usuarios',
                $usuario->id,
                ['via' => 'recovery'],
                ['reset' => true],
            );

            return true;
        });

        if (! $accepted) {
            $this->rejectCode();
        }
    }

    private function findActiveUsuarioByEmail(string $correoElectronico): ?Usuario
    {
        $miembro = Miembro::query()
            ->whereRaw('LOWER(TRIM(correo_electronico)) = ?', [$correoElectronico])
            ->first();

        $usuario = $miembro?->usuario;
        if ($usuario === null || ! $usuario->activo) {
            return null;
        }

        return $usuario;
    }

    private function rejectCode(): never
    {
        throw ValidationException::withMessages([
            'codigo' => self::INVALID_CODE_MESSAGE,
        ]);
    }
}
