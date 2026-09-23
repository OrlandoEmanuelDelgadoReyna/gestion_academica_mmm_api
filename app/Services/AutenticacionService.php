<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Usuario;
use App\Support\UsuarioProfilePhotoStorage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Issues and revokes Sanctum personal-access tokens. */
final class AutenticacionService
{
    public function login(string $username, string $password, ?string $device): array
    {
        $usuario = Usuario::query()->with('roles')->where('nombre_usuario', $username)->first();
        if ($usuario === null || ! $usuario->activo || ! Hash::check($password, $usuario->contrasena)) {
            throw new AuthenticationException('Credenciales inválidas.');
        }
        $usuario->tokens()->where('name', $device ?: 'api')->delete();
        $token = $usuario->createToken($device ?: 'api', ['*'])->plainTextToken;
        $usuario->forceFill(['ultimo_acceso_at' => now()])->save();

        return ['usuario' => $usuario->fresh(['miembro', 'roles']), 'token' => $token];
    }

    public function logout(Usuario $usuario): void
    {
        $usuario->currentAccessToken()?->delete();
    }

    public function changePassword(Usuario $usuario, string $current, string $new): void
    {
        if (! Hash::check($current, $usuario->contrasena)) {
            throw ValidationException::withMessages(['contrasena_actual' => 'La contraseña actual no es válida.']);
        }
        $usuario->forceFill(['contrasena' => $new])->save();
        $usuario->tokens()->delete();
    }

    public function updateProfilePhoto(Usuario $usuario, UploadedFile $archivo): Usuario
    {
        $previousPath = $usuario->profile_photo_path;
        $storedPath = UsuarioProfilePhotoStorage::storeUpload($archivo);

        try {
            $usuario->forceFill(['profile_photo_path' => $storedPath])->save();
        } catch (Throwable $exception) {
            UsuarioProfilePhotoStorage::deleteManaged($storedPath);
            throw $exception;
        }

        if ($previousPath !== $storedPath) {
            UsuarioProfilePhotoStorage::deleteManaged($previousPath);
        }

        return $usuario->fresh(['miembro', 'roles']) ?? $usuario;
    }

    public function deleteProfilePhoto(Usuario $usuario): Usuario
    {
        $previousPath = $usuario->profile_photo_path;
        $usuario->forceFill(['profile_photo_path' => null])->save();
        UsuarioProfilePhotoStorage::deleteManaged($previousPath);

        return $usuario->fresh(['miembro', 'roles']) ?? $usuario;
    }
}
